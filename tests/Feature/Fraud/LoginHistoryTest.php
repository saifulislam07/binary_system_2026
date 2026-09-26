<?php

namespace Tests\Feature\Fraud;

use App\Models\Admin;
use App\Models\LoginHistory;
use App\Models\Member;
use App\Models\Package;
use App\Models\User;
use App\Notifications\NewDeviceLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('rule-12')]
class LoginHistoryTest extends TestCase
{
    use RefreshDatabase;

    private const LAPTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/140.0';

    private const PHONE = 'Mozilla/5.0 (Linux; Android 14; SM-A546E) Chrome/140.0 Mobile';

    private function login(User $user, string $userAgent, string $ip): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->withHeader('User-Agent', $userAgent)
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $this->post(route('logout'));
    }

    public function test_registration_records_ip_and_device()
    {
        $sponsor = Member::factory()->active()->create();

        $this->withServerVariables(['REMOTE_ADDR' => '103.4.145.10'])
            ->withHeader('User-Agent', self::PHONE)
            ->post(route('register.store'), [
                'name' => 'Nusrat Jahan', 'email' => 'nusrat@example.com', 'phone' => '01812345678', 'nid' => '5551234567',
                'address' => 'Mirpur, Dhaka', 'sponsor_code' => $sponsor->member_code, 'preferred_side' => 'right',
                'package_id' => Package::factory()->create()->id, 'password' => 'password', 'password_confirmation' => 'password',
            ]);

        $user = User::query()->where('email', 'nusrat@example.com')->firstOrFail();
        $entry = LoginHistory::query()->where('event', 'registered')->firstOrFail();

        $this->assertSame('web', $entry->guard);
        $this->assertSame($user->id, $entry->authenticatable_id);
        $this->assertSame('103.4.145.10', $entry->ip);
        $this->assertSame(self::PHONE, $entry->user_agent);
    }

    public function test_a_new_device_or_ip_is_flagged_and_the_member_notified_but_not_blocked()
    {
        Notification::fake();
        $user = Member::factory()->active()->create()->user;

        $this->login($user, self::LAPTOP, '103.4.145.10');
        $this->login($user, self::LAPTOP, '103.4.145.10');
        Notification::assertNothingSent();

        $this->withServerVariables(['REMOTE_ADDR' => '37.19.200.1'])
            ->withHeader('User-Agent', self::PHONE)
            ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($user);

        $latest = LoginHistory::query()->latest('id')->firstOrFail();
        $this->assertTrue($latest->new_device);
        $this->assertTrue($latest->new_ip);
        $this->assertSame(3, LoginHistory::query()->where('event', 'login')->count());

        Notification::assertSentToTimes($user, NewDeviceLogin::class, 1);
    }

    public function test_the_very_first_login_is_not_treated_as_a_new_device()
    {
        Notification::fake();
        $user = Member::factory()->active()->create()->user;

        $this->login($user, self::LAPTOP, '103.4.145.10');

        $entry = LoginHistory::query()->firstOrFail();
        $this->assertFalse($entry->new_device);
        $this->assertFalse($entry->new_ip);
        Notification::assertNothingSent();
    }

    public function test_failed_attempts_are_recorded_with_the_email_tried()
    {
        $user = Member::factory()->active()->create()->user;

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);

        $entry = LoginHistory::query()->where('event', 'failed')->firstOrFail();
        $this->assertSame($user->email, $entry->email);
        $this->assertSame($user->id, $entry->authenticatable_id);
        $this->assertGuest();
    }

    public function test_admin_logins_are_recorded_on_the_admin_guard()
    {
        $admin = Admin::factory()->superAdmin()->create();

        $this->post(route('admin.login.store'), ['email' => $admin->email, 'password' => 'password']);

        $entry = LoginHistory::query()->where('guard', 'admin')->firstOrFail();
        $this->assertSame('login', $entry->event);
        $this->assertSame($admin->id, $entry->authenticatable_id);
    }

    public function test_the_member_profile_shows_sign_in_history_to_admins()
    {
        $member = Member::factory()->active()->create();
        $this->login($member->user, self::LAPTOP, '103.4.145.10');
        $this->login($member->user, self::PHONE, '103.4.145.10');

        $this->actingAs(Admin::factory()->superAdmin()->create(), 'admin')
            ->get(route('admin.members.show', $member))
            ->assertOk()
            ->assertSee('Sign-in history')
            ->assertSee('103.4.145.10')
            ->assertSee('new device');
    }
}
