<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

#[Group('rule-12')]
class AdminAccountsTest extends TestCase
{
    use RefreshDatabase;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();

        $this->super = Admin::factory()->superAdmin()->create(['email' => 'owner@example.com']);
    }

    private function signIn(string $email, string $password): void
    {
        $this->post(route('admin.login.store'), ['email' => $email, 'password' => $password]);
    }

    public function test_a_new_admin_gets_their_roles_permissions_and_can_sign_in()
    {
        $this->actingAs($this->super, 'admin')
            ->post(route('admin.admins.store'), [
                'name' => 'Rima Support', 'email' => 'Rima@Example.com', 'role' => 'support',
                'password' => 'Str0ng-pass-123', 'password_confirmation' => 'Str0ng-pass-123',
            ])
            ->assertRedirect(route('admin.admins.index'));

        $rima = Admin::query()->where('email', 'rima@example.com')->firstOrFail();
        $this->assertTrue($rima->hasRole('support'));
        $this->assertTrue(Hash::check('Str0ng-pass-123', $rima->password));

        $log = Activity::query()->where('description', 'Admin account created')->firstOrFail();
        $this->assertTrue($log->causer?->is($this->super));
        $this->assertStringNotContainsString('Str0ng', json_encode($log->properties) ?: '', 'Passwords never reach the audit log');

        $this->post(route('admin.logout'));
        $this->signIn('rima@example.com', 'Str0ng-pass-123');
        $this->assertAuthenticatedAs($rima, 'admin');
        $this->get(route('admin.members.index'))->assertOk();
        $this->get(route('admin.admins.index'))->assertForbidden();
    }

    public function test_a_deactivated_admin_is_signed_out_and_cannot_sign_in_again()
    {
        $clerk = Admin::factory()->create(['email' => 'clerk@example.com'])->assignRole('finance');

        $this->actingAs($clerk, 'admin')->get(route('admin.sales.index'))->assertOk();

        $this->actingAs($this->super, 'admin')
            ->put(route('admin.admins.update', $clerk), ['name' => $clerk->name, 'email' => $clerk->email, 'role' => 'finance', 'is_active' => '0'])
            ->assertSessionHasNoErrors();

        $this->actingAs($clerk->fresh() ?? $clerk, 'admin')
            ->get(route('admin.sales.index'))
            ->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');

        $this->signIn('clerk@example.com', 'password');
        $this->assertGuest('admin');
    }

    public function test_an_admin_cannot_lock_themselves_out()
    {
        $this->actingAs($this->super, 'admin')
            ->put(route('admin.admins.update', $this->super), ['name' => 'Owner', 'email' => 'owner@example.com', 'role' => 'admin', 'is_active' => '0'])
            ->assertSessionHas('error');

        $this->actingAs($this->super, 'admin')
            ->put(route('admin.admins.update', $this->super), ['name' => 'Owner', 'email' => 'owner@example.com', 'role' => 'support', 'is_active' => '1'])
            ->assertSessionHas('error');

        $this->super->refresh();
        $this->assertTrue($this->super->is_active);
        $this->assertTrue($this->super->hasRole('admin'));
    }

    public function test_someone_must_always_be_able_to_manage_admins()
    {
        // A custom role that can manage admins, held by the only other manager.
        $this->actingAs($this->super, 'admin')
            ->post(route('admin.roles.store'), ['name' => 'boss', 'permissions' => ['manage-admins', 'view-reports']])
            ->assertRedirect(route('admin.admins.index'))
            ->assertSessionHasNoErrors();
        $boss = Admin::factory()->create()->assignRole('boss');

        $this->actingAs($boss, 'admin')
            ->put(route('admin.admins.update', $this->super), ['name' => 'Owner', 'email' => 'owner@example.com', 'role' => 'admin', 'is_active' => '0'])
            ->assertRedirect(route('admin.admins.index'))
            ->assertSessionHasNoErrors();
        $this->assertFalse($this->super->fresh()?->is_active);

        // The seeded first admin (ReferenceDataSeeder) is a manager too — take it out of the picture.
        Admin::query()->whereKeyNot([$boss->id, $this->super->id])->update(['is_active' => false]);

        // Now boss is the last manager: taking manage-admins off their role is refused.
        $role = Role::findByName('boss', 'admin');
        $this->actingAs($boss, 'admin')
            ->put(route('admin.roles.update', $role), ['permissions' => ['view-reports']])
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'manage admins'));

        $this->assertTrue($role->fresh()?->hasPermissionTo('manage-admins'));
    }

    public function test_role_permissions_can_be_changed_except_the_super_admin_role()
    {
        $support = Role::findByName('support', 'admin');

        $this->actingAs($this->super, 'admin')
            ->put(route('admin.roles.update', $support), ['permissions' => ['manage-members', 'manage-kyc', 'view-reports']])
            ->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing(['manage-members', 'manage-kyc', 'view-reports'], $support->fresh()?->permissions->pluck('name')->all());

        $log = Activity::query()->where('description', 'Admin role permissions changed')->firstOrFail();
        $this->assertSame(['manage-kyc', 'manage-members'], $log->properties['old']);

        $this->actingAs($this->super, 'admin')
            ->put(route('admin.roles.update', Role::findByName('admin', 'admin')), ['permissions' => []])
            ->assertSessionHas('error');
        $this->assertTrue(Role::findByName('admin', 'admin')->hasPermissionTo('manage-admins'));
    }

    public function test_an_admin_can_reset_another_admins_password()
    {
        $clerk = Admin::factory()->create(['email' => 'clerk@example.com'])->assignRole('finance');

        $this->actingAs($this->super, 'admin')
            ->put(route('admin.admins.update', $clerk), [
                'name' => $clerk->name, 'email' => $clerk->email, 'role' => 'finance', 'is_active' => '1',
                'password' => 'Br4nd-new-pass', 'password_confirmation' => 'Br4nd-new-pass',
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('Br4nd-new-pass', (string) $clerk->fresh()?->password));
        $this->assertTrue(Activity::query()->where('description', 'Admin account updated')->latest('id')->firstOrFail()->properties['password_reset']);
    }

    public function test_every_admin_can_change_their_own_password()
    {
        $clerk = Admin::factory()->create()->assignRole('finance');
        $this->actingAs($clerk, 'admin');

        $this->get(route('admin.account.edit'))->assertOk()->assertSee('Change password');

        $this->put(route('admin.account.password'), ['current_password' => 'wrong', 'password' => 'N3w-pass-word', 'password_confirmation' => 'N3w-pass-word'])
            ->assertSessionHasErrors('current_password');

        $this->put(route('admin.account.password'), ['current_password' => 'password', 'password' => 'N3w-pass-word', 'password_confirmation' => 'N3w-pass-word'])
            ->assertRedirect(route('admin.account.edit'));

        $this->assertTrue(Hash::check('N3w-pass-word', (string) $clerk->fresh()?->password));
    }

    public function test_only_admin_managers_see_the_accounts_pages()
    {
        $this->actingAs($this->super, 'admin')->get(route('admin.admins.index'))->assertOk()->assertSee('owner@example.com');
        $this->actingAs($this->super, 'admin')->get(route('admin.admins.create'))->assertOk();

        $support = Admin::factory()->create()->assignRole('support');
        $this->actingAs($support, 'admin')->get(route('admin.admins.index'))->assertForbidden();
        $this->actingAs($support, 'admin')->post(route('admin.roles.store'), ['name' => 'sneaky', 'permissions' => ['manage-admins']])->assertForbidden();
        $this->assertNull(Role::query()->where('name', 'sneaky')->first());
    }
}
