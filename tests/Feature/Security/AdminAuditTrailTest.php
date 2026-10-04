<?php

namespace Tests\Feature\Security;

use App\Enums\ExpenseCategory;
use App\Enums\PlacementSide;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalMethodType;
use App\Http\Controllers\Admin\FinancialController;
use App\Models\Admin;
use App\Models\BonusRule;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Expense;
use App\Models\FraudFlag;
use App\Models\IncomeTransaction;
use App\Models\KycDocument;
use App\Models\Member;
use App\Models\MembershipSection;
use App\Models\Package;
use App\Models\Product;
use App\Models\Rank;
use App\Models\Withdrawal;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use PHPUnit\Framework\Attributes\Group;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\Support\BuildsNetwork;
use Tests\TestCase;

/**
 * Rule #12: every admin action that changes money, tree structure or member
 * status leaves an audit entry naming the admin. The route list is checked
 * too, so a new admin write endpoint fails here until it is covered.
 */
#[Group('rule-12')]
class AdminAuditTrailTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private const BKASH = ['provider' => 'bkash', 'mobile_number' => '+8801712345678'];

    /** Session plumbing, not business actions. */
    private const NOT_AUDITED = ['admin.login.store', 'admin.two-factor.verify', 'admin.logout'];

    private Admin $admin;

    private Member $root;

    private Member $left;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCommissionRules();
        $this->admin = Admin::factory()->superAdmin()->create();
        $this->root = $this->root();
        $this->left = $this->join($this->root, PlacementSide::Left);
    }

    private function withdrawal(): Withdrawal
    {
        app(WalletService::class)->credit($this->left, 500_000, WalletTransactionType::ReferralBonus);

        return app(WithdrawalService::class)->request($this->left, 150_000, WithdrawalMethodType::MobileBanking, self::BKASH);
    }

    /**
     * Route name => [method, url, payload] built against fresh data.
     *
     * @return array<string, callable(): array{0: string, 1: string, 2: array<string, mixed>}>
     */
    private function actions(): array
    {
        return [
            'admin.members.update' => fn () => ['PUT', route('admin.members.update', $this->left), [
                'name' => 'Renamed Member', 'email' => 'renamed@example.com', 'phone' => '01911111111', 'nid' => '1234567890', 'address' => 'Khulna',
            ]],
            'admin.members.suspend' => fn () => ['POST', route('admin.members.suspend', $this->left), ['reason' => 'Duplicate account']],
            'admin.members.reinstate' => function () {
                $this->post(route('admin.members.suspend', $this->left), ['reason' => 'Temporary hold']);

                return ['POST', route('admin.members.reinstate', $this->left), ['reason' => 'Cleared']];
            },
            'admin.members.package' => fn () => ['POST', route('admin.members.package', $this->left), [
                'package_id' => Package::query()->where('name', 'Premium')->value('id'), 'reason' => 'Upgrade agreed by phone',
            ]],
            'admin.members.activate' => fn () => ['POST', route('admin.members.activate', Member::factory()->create(['sponsor_id' => $this->root->id])), []],
            'admin.members.performance-bonus' => fn () => ['POST', route('admin.members.performance-bonus', $this->left), ['amount' => '500', 'reason' => 'Top seller in September']],
            'admin.tree.adjust' => function () {
                $mover = $this->join($this->left, PlacementSide::Left);

                return ['POST', route('admin.tree.adjust', $mover->member_code), [
                    'new_parent' => $this->root->member_code, 'side' => 'right', 'reason' => 'Placed on the wrong leg at signup',
                ]];
            },
            'admin.sales.refund' => fn () => ['POST', route('admin.sales.refund', $this->sell($this->left, 1_000)), ['reason' => 'Product returned']],
            'admin.withdrawals.approve' => fn () => ['POST', route('admin.withdrawals.approve', $this->withdrawal()), []],
            'admin.withdrawals.processing' => function () {
                $w = $this->withdrawal();
                app(WithdrawalService::class)->approve($w, $this->admin);

                return ['POST', route('admin.withdrawals.processing', $w), []];
            },
            'admin.withdrawals.paid' => function () {
                $w = $this->withdrawal();
                app(WithdrawalService::class)->approve($w, $this->admin);
                app(WithdrawalService::class)->startProcessing($w, $this->admin);

                return ['POST', route('admin.withdrawals.paid', $w), ['payout_reference' => 'BK-TRX-99']];
            },
            'admin.withdrawals.reject' => fn () => ['POST', route('admin.withdrawals.reject', $this->withdrawal()), ['reason' => 'Name mismatch']],
            'admin.kyc.approve' => fn () => ['POST', route('admin.kyc.approve', KycDocument::factory()->create(['member_id' => $this->left->id])), []],
            'admin.kyc.reject' => fn () => ['POST', route('admin.kyc.reject', KycDocument::factory()->create(['member_id' => $this->left->id])), ['reason' => 'Blurred']],
            'admin.fraud.review' => fn () => ['POST', route('admin.fraud.review', FraudFlag::raise($this->left, FraudFlag::RAPID_WITHDRAWAL, null, [])), [
                'outcome' => 'reviewed', 'note' => 'Checked with the member',
            ]],
            'admin.financial.expenses.store' => fn () => ['POST', route('admin.financial.expenses.store'), [
                'category' => ExpenseCategory::Delivery->value, 'amount' => '250', 'date' => now()->toDateString(), 'description' => 'Courier',
            ]],
            'admin.financial.expenses.destroy' => fn () => ['DELETE', route('admin.financial.expenses.destroy', Expense::factory()->create()), []],
            'admin.financial.income.store' => fn () => ['POST', route('admin.financial.income.store'), [
                'source' => array_key_first(FinancialController::INCOME_SOURCES), 'amount' => '100', 'date' => now()->toDateString(), 'description' => 'Bank interest',
            ]],
            'admin.financial.income.destroy' => fn () => ['DELETE', route('admin.financial.income.destroy', IncomeTransaction::factory()->create()), []],
            'admin.settings.update' => fn () => ['PUT', route('admin.settings.update'), [
                'binary_rate' => '11', 'referral_rate' => '5', 'daily_cap' => '5000', 'weekly_cap' => '20000', 'monthly_cap' => '50000',
                'carry_forward_enabled' => '1', 'cap_overflow_behavior' => 'void', 'min_withdrawal' => '1000',
            ]],
            'admin.announcements.store' => fn () => ['POST', route('admin.announcements.store'), ['title' => 'Notice', 'body' => 'Office closed', 'audience' => 'active']],
            'admin.packages.store' => fn () => ['POST', route('admin.packages.store'), [
                'name' => 'Gold Pack', 'price' => '15000', 'bv_value' => '12000', 'cost_of_goods' => '4000',
                'is_qualifying' => '1', 'is_active' => '1', 'sort_order' => '9',
            ]],
            'admin.packages.update' => function () {
                $package = Package::query()->where('name', 'Basic')->firstOrFail();

                return ['PUT', route('admin.packages.update', $package), [
                    'name' => 'Basic', 'price' => '1200', 'bv_value' => '1000', 'cost_of_goods' => '300',
                    'is_qualifying' => '1', 'is_active' => '1', 'sort_order' => (string) $package->sort_order,
                ]];
            },
            'admin.ranks.update' => function () {
                $ranks = Rank::query()->orderBy('sort_order')->get()->mapWithKeys(fn (Rank $r) => [$r->id => [
                    'min_personal_sales' => Money::toInputString($r->min_personal_sales),
                    'min_team_sales' => Money::toInputString($r->min_team_sales),
                    'min_active_team' => (string) $r->min_active_team,
                    'bonus_amount' => Money::toInputString($r->bonus_amount + 10_000),
                ]])->all();

                return ['PUT', route('admin.ranks.update'), ['ranks' => $ranks]];
            },
            'admin.bonus-rules.store' => fn () => ['POST', route('admin.bonus-rules.store'), [
                'type' => BonusRule::LEADERSHIP, 'name' => 'Leadership: 200 active', 'threshold' => '200', 'amount' => '30000', 'is_active' => '1',
            ]],
            'admin.bonus-rules.update' => fn () => ['PUT', route('admin.bonus-rules.update', BonusRule::query()->firstOrFail()), [
                'name' => 'Renamed rule', 'threshold' => '15', 'amount' => '2500', 'is_active' => '0',
            ]],
            'admin.admins.store' => fn () => ['POST', route('admin.admins.store'), [
                'name' => 'Nasrin Finance', 'email' => 'nasrin@example.com', 'role' => 'finance',
                'password' => 'Sup3r-secret-pass', 'password_confirmation' => 'Sup3r-secret-pass',
            ]],
            'admin.admins.update' => fn () => ['PUT', route('admin.admins.update', Admin::factory()->create()->assignRole('support')), [
                'name' => 'Promoted', 'email' => 'promoted@example.com', 'role' => 'finance', 'is_active' => '1',
            ]],
            'admin.roles.store' => fn () => ['POST', route('admin.roles.store'), ['name' => 'accounts', 'permissions' => ['view-reports']]],
            'admin.roles.update' => fn () => ['PUT', route('admin.roles.update', Role::findByName('support', 'admin')), ['permissions' => ['manage-members']]],
            'admin.categories.store' => fn () => ['POST', route('admin.categories.store'), ['name' => 'Audio', 'sort_order' => '1', 'is_active' => '1']],
            'admin.categories.update' => fn () => ['PUT', route('admin.categories.update', Category::query()->create(['name' => 'Gadgets'])), [
                'name' => 'Gadgets & more', 'sort_order' => '2', 'is_active' => '1',
            ]],
            'admin.membership.store' => fn () => ['POST', route('admin.membership.store'), [
                'title_en' => 'Who can join', 'body_en' => '<p>Anyone.</p>', 'sort_order' => '1', 'is_active' => '1',
            ]],
            'admin.membership.update' => fn () => ['PUT', route('admin.membership.update', MembershipSection::query()->create(['title_en' => 'Old', 'body_en' => '<p>Old.</p>'])), [
                'title_en' => 'New', 'body_en' => '<p>New.</p>', 'sort_order' => '2', 'is_active' => '1',
            ]],
            'admin.brands.store' => fn () => ['POST', route('admin.brands.store'), ['name' => 'Sonic', 'sort_order' => '1', 'is_active' => '1']],
            'admin.brands.update' => fn () => ['PUT', route('admin.brands.update', Brand::factory()->create()), [
                'name' => 'Renamed brand', 'sort_order' => '2', 'is_active' => '1',
            ]],
            'admin.products.store' => fn () => ['POST', route('admin.products.store'), [
                'name' => 'Earbuds', 'sku' => 'EB-1', 'price' => '2450', 'is_active' => '1', 'is_featured' => '0', 'sort_order' => '1',
            ]],
            'admin.products.update' => fn () => ['PUT', route('admin.products.update', Product::factory()->create()), [
                'name' => 'Renamed product', 'sku' => 'EB-2', 'price' => '999', 'is_active' => '1', 'is_featured' => '1', 'sort_order' => '1',
            ]],
            'admin.account.two-factor.store' => fn () => ['POST', route('admin.account.two-factor.store'), []],
            'admin.account.two-factor.confirm' => function () {
                $this->admin->forceFill(['two_factor_secret' => $secret = app(Google2FA::class)->generateSecretKey(32), 'two_factor_confirmed_at' => null])->save();

                return ['POST', route('admin.account.two-factor.confirm'), ['code' => app(Google2FA::class)->getCurrentOtp($secret)]];
            },
            'admin.account.two-factor.recovery-codes' => fn () => ['POST', route('admin.account.two-factor.recovery-codes'), ['current_password' => 'password']],
            'admin.account.two-factor.destroy' => fn () => ['DELETE', route('admin.account.two-factor.destroy'), ['current_password' => 'password']],
            'admin.admins.two-factor.reset' => function () {
                $other = Admin::factory()->create()->assignRole('support');
                $other->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();

                return ['DELETE', route('admin.admins.two-factor.reset', $other), []];
            },
            'admin.account.password' => fn () => ['PUT', route('admin.account.password'), [
                'current_password' => 'password', 'password' => 'N3w-admin-pass', 'password_confirmation' => 'N3w-admin-pass',
            ]],
        ];
    }

    public function test_every_admin_write_endpoint_is_covered_here()
    {
        $writes = collect(Router::getRoutes()->getRoutes())
            ->filter(fn (Route $r) => str_starts_with($r->uri(), 'admin/') && array_diff($r->methods(), ['GET', 'HEAD']) !== [])
            ->map(fn (Route $r) => (string) $r->getName())
            ->reject(fn (string $name) => in_array($name, self::NOT_AUDITED, true))
            ->sort()->values()->all();

        $covered = collect(array_keys($this->actions()))->sort()->values()->all();

        $this->assertSame($writes, $covered, 'A new admin write endpoint needs an audit-trail case here.');
    }

    public function test_every_admin_write_is_audited_with_the_admin_as_causer()
    {
        $this->actingAs($this->admin, 'admin');

        foreach ($this->actions() as $name => $action) {
            [$method, $url, $payload] = $action();
            $before = (int) Activity::query()->max('id');

            $this->call($method, $url, $payload)->assertRedirect()->assertSessionHasNoErrors()->assertSessionMissing('error');

            $entries = Activity::query()->with('causer')->where('id', '>', $before)->get();
            $this->assertTrue(
                $entries->contains(fn (Activity $a) => $a->causer instanceof Admin && $a->causer->is($this->admin)),
                "{$name} left no audit entry caused by the admin (got: ".$entries->pluck('description')->implode(', ').')',
            );
        }
    }
}
