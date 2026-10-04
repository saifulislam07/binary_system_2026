<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\LoginHistory;
use App\Services\AdminTwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private Google2FA $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new Google2FA;
    }

    /**
     * @return array{Admin, list<string>}
     */
    private function adminWithTwoFactor(): array
    {
        $admin = Admin::factory()->superAdmin()->create();
        $service = app(AdminTwoFactorService::class);
        $service->begin($admin);
        $codes = $service->confirm($admin, $this->engine->getCurrentOtp((string) $admin->two_factor_secret));

        // A fresh code slot for the sign-in that follows.
        Cache::forget("admin-2fa-used:{$admin->id}");

        return [$admin->fresh(), $codes];
    }

    private function currentCode(Admin $admin): string
    {
        return $this->engine->getCurrentOtp((string) $admin->two_factor_secret);
    }

    public function test_an_admin_sets_up_two_factor_from_their_account()
    {
        $admin = Admin::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.account.two-factor.store'))->assertRedirect(route('admin.account.edit'));
        $admin->refresh();
        $this->assertNotNull($admin->two_factor_secret);
        $this->assertFalse($admin->hasTwoFactorEnabled(), 'not on until a code is confirmed');

        $this->get(route('admin.account.edit'))->assertOk()->assertSee('<svg', false)->assertSee($admin->two_factor_secret);

        $this->post(route('admin.account.two-factor.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertFalse($admin->fresh()->hasTwoFactorEnabled());

        $this->post(route('admin.account.two-factor.confirm'), ['code' => $this->currentCode($admin)])
            ->assertRedirect(route('admin.account.edit'))
            ->assertSessionHas('recoveryCodes', fn (array $codes) => count($codes) === AdminTwoFactorService::RECOVERY_CODES);

        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());
        $this->assertTrue(Activity::query()->where('description', 'Admin turned on two-factor sign-in')->where('causer_id', $admin->id)->exists());
    }

    public function test_the_secret_and_recovery_codes_are_stored_encrypted()
    {
        [$admin, $codes] = $this->adminWithTwoFactor();

        $raw = (array) DB::table('admins')->where('id', $admin->id)->first(['two_factor_secret', 'two_factor_recovery_codes']);

        $this->assertStringNotContainsString((string) $admin->two_factor_secret, (string) $raw['two_factor_secret']);
        $this->assertStringNotContainsString($codes[0], (string) $raw['two_factor_recovery_codes']);
    }

    public function test_sign_in_asks_for_a_code_before_any_session_starts()
    {
        [$admin] = $this->adminWithTwoFactor();

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.two-factor.challenge'));
        $this->assertGuest('admin');
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));

        $this->get(route('admin.two-factor.challenge'))->assertOk()->assertSee('Enter your sign-in code');

        $this->post(route('admin.two-factor.verify'), ['code' => $this->currentCode($admin)])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertNotNull($admin->fresh()->last_login_at);
        $this->assertSame('login', LoginHistory::query()->where('guard', 'admin')->latest('id')->value('event'));
    }

    public function test_a_wrong_code_is_refused_recorded_and_rate_limited()
    {
        [$admin] = $this->adminWithTwoFactor();
        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.two-factor.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
        }

        $this->assertGuest('admin');
        $this->assertSame(5, LoginHistory::query()->where('guard', 'admin')->where('event', 'failed')->where('authenticatable_id', $admin->id)->count());

        // Locked out now, even with the right code.
        $this->post(route('admin.two-factor.verify'), ['code' => $this->currentCode($admin)])->assertSessionHasErrors('code');
        $this->assertGuest('admin');
    }

    public function test_a_code_works_only_once()
    {
        [$admin] = $this->adminWithTwoFactor();
        $code = $this->currentCode($admin);

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password']);
        $this->post(route('admin.two-factor.verify'), ['code' => $code])->assertRedirect(route('admin.dashboard'));
        $this->post(route('admin.logout'));

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password']);
        $this->post(route('admin.two-factor.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest('admin');
    }

    public function test_a_recovery_code_signs_in_once()
    {
        [$admin, $codes] = $this->adminWithTwoFactor();

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password']);
        $this->post(route('admin.two-factor.verify'), ['recovery_code' => strtoupper($codes[0])])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertCount(AdminTwoFactorService::RECOVERY_CODES - 1, $admin->fresh()->two_factor_recovery_codes ?? []);
        $this->post(route('admin.logout'));

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password']);
        $this->post(route('admin.two-factor.verify'), ['recovery_code' => $codes[0]])->assertSessionHasErrors('code');
        $this->assertGuest('admin');
    }

    public function test_the_challenge_needs_a_recent_password_step()
    {
        [$admin] = $this->adminWithTwoFactor();

        $this->get(route('admin.two-factor.challenge'))->assertRedirect(route('admin.login'));
        $this->post(route('admin.two-factor.verify'), ['code' => $this->currentCode($admin)])->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password']);
        $this->travel(11)->minutes();

        $this->post(route('admin.two-factor.verify'), ['code' => $this->currentCode($admin)])->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_an_admin_deactivated_mid_sign_in_cannot_finish()
    {
        [$admin] = $this->adminWithTwoFactor();
        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password']);

        $admin->forceFill(['is_active' => false])->save();

        $this->post(route('admin.two-factor.verify'), ['code' => $this->currentCode($admin)])->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_turning_two_factor_off_needs_the_password()
    {
        [$admin] = $this->adminWithTwoFactor();
        $this->actingAs($admin, 'admin');

        $this->delete(route('admin.account.two-factor.destroy'), ['current_password' => 'wrong'])->assertSessionHasErrors('current_password');
        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());

        $this->delete(route('admin.account.two-factor.destroy'), ['current_password' => 'password'])->assertSessionHasNoErrors();
        $admin->refresh();
        $this->assertFalse($admin->hasTwoFactorEnabled());
        $this->assertNull($admin->two_factor_secret);
    }

    public function test_new_recovery_codes_replace_the_old_ones()
    {
        [$admin, $old] = $this->adminWithTwoFactor();
        $this->actingAs($admin, 'admin');

        $this->post(route('admin.account.two-factor.recovery-codes'), ['current_password' => 'password'])
            ->assertSessionHas('recoveryCodes');

        $this->assertNotContains($old[0], $admin->fresh()->two_factor_recovery_codes ?? []);
    }

    public function test_an_admin_manager_can_reset_another_admins_two_factor_but_not_their_own_there()
    {
        [$target] = $this->adminWithTwoFactor();
        $manager = Admin::factory()->superAdmin()->create();
        $this->actingAs($manager, 'admin');

        $this->get(route('admin.admins.edit', $target))->assertOk()->assertSee('Reset two-factor');

        $this->delete(route('admin.admins.two-factor.reset', $target))->assertRedirect(route('admin.admins.edit', $target));
        $this->assertFalse($target->fresh()->hasTwoFactorEnabled());
        $this->assertTrue(Activity::query()->where('description', "Admin reset two-factor sign-in for {$target->email}")->where('causer_id', $manager->id)->exists());

        $this->delete(route('admin.admins.two-factor.reset', $manager))->assertSessionHas('error');
    }

    public function test_support_admins_cannot_reset_two_factor()
    {
        [$target] = $this->adminWithTwoFactor();
        $support = Admin::factory()->create()->assignRole('support');

        $this->actingAs($support, 'admin')->delete(route('admin.admins.two-factor.reset', $target))->assertForbidden();
        $this->assertTrue($target->fresh()->hasTwoFactorEnabled());
    }

    public function test_when_required_an_admin_without_two_factor_can_only_reach_their_account()
    {
        config(['business.admin_two_factor_required' => true]);
        $admin = Admin::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.account.edit'));
        $this->get(route('admin.members.index'))->assertRedirect(route('admin.account.edit'));
        $this->get(route('admin.account.edit'))->assertOk()->assertSee('Required');

        [$ready] = $this->adminWithTwoFactor();
        $this->actingAs($ready, 'admin')->get(route('admin.dashboard'))->assertOk();
    }
}
