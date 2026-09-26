<?php

namespace Tests\Feature\Security;

use App\Enums\AdminPermission;
use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\CommissionRule;
use App\Models\KycDocument;
use App\Models\Member;
use App\Models\Package;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use App\Services\WalletService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Inertia\Testing\AssertableInertia as Assert;
use ReflectionClass;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Admin routes that only need a signed-in admin (or none, for login).
     */
    private const UNGATED_ADMIN_ROUTES = [
        'admin.login' => 'guest', 'admin.login.store' => 'guest',
        'admin.logout' => 'auth', 'admin.dashboard' => 'auth', 'admin.' => 'auth',
    ];

    /**
     * @return list<Route>
     */
    private function adminRoutes(): array
    {
        return array_values(array_filter(
            Router::getRoutes()->getRoutes(),
            fn (Route $route) => $route->uri() === 'admin' || str_starts_with($route->uri(), 'admin/'),
        ));
    }

    public function test_every_admin_route_requires_an_admin_and_a_permission()
    {
        $permissions = AdminPermission::values();
        $routes = $this->adminRoutes();
        $this->assertGreaterThan(40, count($routes));

        foreach ($routes as $route) {
            $name = (string) $route->getName();
            $middleware = $route->gatherMiddleware();

            if ((self::UNGATED_ADMIN_ROUTES[$name] ?? null) === 'guest') {
                $this->assertContains('guest:admin', $middleware, "{$name} should be guest-only");

                continue;
            }

            $this->assertContains('auth:admin', $middleware, "{$name} ({$route->uri()}) is not behind auth:admin");

            if (! array_key_exists($name, self::UNGATED_ADMIN_ROUTES)) {
                $gates = array_values(array_filter($middleware, fn ($m) => is_string($m) && str_starts_with($m, 'can:')));
                $this->assertNotEmpty($gates, "{$name} ({$route->uri()}) has no can: permission gate");

                foreach ($gates as $gate) {
                    $this->assertContains(substr($gate, 4), $permissions, "{$name} is gated by an unknown permission {$gate}");
                }
            }
        }
    }

    public function test_an_admin_with_no_permissions_is_refused_everywhere_but_the_dashboard()
    {
        $nobody = Admin::factory()->create();
        $this->actingAs($nobody, 'admin');

        foreach ($this->adminRoutes() as $route) {
            $name = (string) $route->getName();

            if (array_key_exists($name, self::UNGATED_ADMIN_ROUTES) || ! in_array('GET', $route->methods(), true)
                || str_contains($route->uri(), '{')) {
                continue;
            }

            $this->get('/'.$route->uri())->assertForbidden();
        }

        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_every_model_is_protected_from_mass_assignment()
    {
        $models = collect(glob(app_path('Models/*.php')) ?: [])
            ->map(fn (string $file) => 'App\\Models\\'.basename($file, '.php'))
            ->filter(fn (string $class) => is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract());

        $this->assertGreaterThan(25, $models->count());

        foreach ($models as $class) {
            /** @var Model $model */
            $model = new $class;
            $this->assertNotSame([], $model->getGuarded(), "{$class} is fully unguarded");
            $this->assertNotEmpty($model->getFillable(), "{$class} declares no #[Fillable] list (would reject everything or rely on \$guarded)");
            $this->assertTrue($model->isGuarded('id'), "{$class} lets id be mass-assigned");
        }
    }

    public function test_money_status_and_tree_columns_are_never_mass_assignable()
    {
        $protected = [
            Member::class => ['member_code', 'status', 'placement_parent_id', 'placement_side', 'activated_at', 'current_rank_id'],
            User::class => ['is_active', 'email_verified_at', 'remember_token'],
            Wallet::class => ['balance'],
            Withdrawal::class => ['status', 'admin_id', 'wallet_transaction_id', 'processed_at', 'payout_reference', 'rejection_reason'],
            KycDocument::class => ['status', 'reviewed_by', 'reviewed_at', 'rejection_reason'],
        ];

        foreach ($protected as $class => $columns) {
            $model = new $class;

            foreach ($columns as $column) {
                $this->assertFalse($model->isFillable($column), "{$class}::{$column} must only be set with forceFill() by its service");
            }
        }

        // And fill() really ignores them.
        $member = Member::factory()->create();
        $member->fill(['status' => 'active', 'member_code' => 'MBR-999999', 'address' => 'Sylhet'])->save();
        $member->refresh();
        $this->assertSame('pending', $member->status->value);
        $this->assertNull($member->member_code);
        $this->assertSame('Sylhet', $member->address);
    }

    public function test_registration_is_rate_limited()
    {
        $sponsor = Member::factory()->active()->create();
        $package = Package::factory()->create();

        foreach (range(1, 5) as $i) {
            $this->post(route('register.store'), ['sponsor_code' => $sponsor->member_code, 'package_id' => $package->id])
                ->assertStatus(302); // validation errors, but allowed through
        }

        $this->post(route('register.store'), ['sponsor_code' => $sponsor->member_code])->assertStatus(429);
    }

    public function test_password_reset_requests_are_rate_limited()
    {
        foreach (range(1, 5) as $i) {
            $this->post(route('password.email'), ['email' => "someone{$i}@example.com"])->assertStatus(302);
        }

        $this->post(route('password.email'), ['email' => 'someone9@example.com'])->assertStatus(429);
    }

    public function test_withdrawal_requests_are_rate_limited()
    {
        $member = Member::factory()->active()->create();
        app(WalletService::class)->credit($member, 10_000_000, WalletTransactionType::ReferralBonus);
        $this->actingAs($member->user);

        foreach (range(1, 5) as $i) {
            $this->post(route('withdrawals.store'), ['amount' => '1'])->assertStatus(302); // below minimum
        }

        $this->post(route('withdrawals.store'), ['amount' => '1'])->assertStatus(429);
    }

    public function test_members_cannot_delete_their_account_themselves()
    {
        $member = Member::factory()->active()->create();

        $this->actingAs($member->user)
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('canDeleteAccount', false));

        $this->actingAs($member->user)
            ->delete(route('profile.destroy'), ['password' => 'password'])
            ->assertSessionHasErrors('password');

        $this->assertNotNull($member->user->fresh());
        $this->assertAuthenticatedAs($member->user);
    }

    public function test_settings_parse_money_and_rates_without_floats()
    {
        $admin = Admin::factory()->superAdmin()->create();
        $valid = [
            'binary_rate' => '12.35', 'referral_rate' => '0.29', 'daily_cap' => '5000.10', 'weekly_cap' => '20000',
            'monthly_cap' => '0', 'carry_forward_enabled' => '1', 'cap_overflow_behavior' => 'void', 'min_withdrawal' => '1000.01',
        ];

        $this->actingAs($admin, 'admin')->put(route('admin.settings.update'), $valid)->assertSessionHasNoErrors();

        $rules = CommissionRule::query()->pluck('value', 'key');
        $this->assertSame('1235', $rules[CommissionRule::BINARY_RATE_BPS]);
        $this->assertSame('29', $rules[CommissionRule::REFERRAL_RATE_BPS], '0.29% is exactly 29 bps');
        $this->assertSame('500010', $rules[CommissionRule::DAILY_CAP]);
        $this->assertSame(100_001, Setting::int(Setting::MIN_WITHDRAWAL, 0));

        $this->actingAs($admin, 'admin')
            ->put(route('admin.settings.update'), [...$valid, 'binary_rate' => '10.125', 'daily_cap' => '1e3'])
            ->assertSessionHasErrors(['binary_rate', 'daily_cap']);
    }
}
