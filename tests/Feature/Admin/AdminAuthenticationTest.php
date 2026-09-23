<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminPermission;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdminAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_screen_can_be_rendered()
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('Sign in to the admin panel');
    }

    public function test_admin_can_log_in_and_see_the_dashboard()
    {
        $admin = Admin::factory()->superAdmin()->create();

        $response = $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertGuest('web');
        $this->assertNotNull($admin->fresh()->last_login_at);

        $this->get(route('admin.dashboard'))->assertOk()->assertSee($admin->name);
    }

    public function test_admin_cannot_log_in_with_wrong_password()
    {
        $admin = Admin::factory()->create();

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_inactive_admin_cannot_log_in()
    {
        $admin = Admin::factory()->inactive()->create();

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_member_credentials_do_not_work_on_admin_login()
    {
        $user = User::factory()->create();

        $this->post(route('admin.login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_logged_in_member_cannot_access_admin_dashboard()
    {
        $this->actingAs(User::factory()->create(), 'web')
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_guests_are_redirected_to_admin_login()
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_log_out()
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest('admin');
    }

    public function test_admin_login_is_rate_limited()
    {
        $admin = Admin::factory()->create();

        RateLimiter::increment('admin-login|'.strtolower($admin->email).'|127.0.0.1', amount: 5);

        $this->post(route('admin.login.store'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('admin');
    }

    public function test_admin_role_holds_every_admin_permission()
    {
        $admin = Admin::factory()->superAdmin()->create();

        foreach (AdminPermission::cases() as $permission) {
            $this->assertTrue($admin->can($permission->value), $permission->value);
        }
    }
}
