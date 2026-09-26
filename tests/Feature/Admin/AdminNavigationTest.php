<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The sidebar and the routes behind it are gated by the same permissions:
 * a limited admin neither sees nor can open another role's sections.
 */
class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Section route => permission guarding it.
     */
    private const SECTIONS = [
        'admin.members.index' => 'manage-members',
        'admin.members.pending' => 'manage-members',
        'admin.fraud.index' => 'manage-members',
        'admin.tree.index' => 'manage-tree',
        'admin.sales.index' => 'manage-sales',
        'admin.financial.index' => 'view-reports',
        'admin.withdrawals.index' => 'manage-withdrawals',
        'admin.kyc.index' => 'manage-kyc',
        'admin.reports.index' => 'view-reports',
        'admin.settings.index' => 'manage-settings',
        'admin.audit.index' => 'manage-settings',
        'admin.announcements.index' => 'send-announcements',
    ];

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function roles(): array
    {
        return [
            'super admin' => ['admin'],
            'support' => ['support'],
            'finance' => ['finance'],
            'no role' => [null],
        ];
    }

    #[DataProvider('roles')]
    public function test_menu_and_routes_follow_the_admins_permissions(?string $role)
    {
        $admin = Admin::factory()->create();

        if ($role !== null) {
            $admin->assignRole($role);
        }

        $dashboard = $this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk();

        foreach (self::SECTIONS as $route => $permission) {
            $allowed = $admin->can($permission);

            // Menu link present exactly when permitted.
            $allowed
                ? $dashboard->assertSee(route($route), false)
                : $dashboard->assertDontSee(route($route), false);

            // And the route itself agrees.
            $this->actingAs($admin, 'admin')->get(route($route))->assertStatus($allowed ? 200 : 403);
        }
    }

    public function test_limited_roles_have_the_expected_permissions()
    {
        $support = Admin::factory()->create()->assignRole('support');
        $finance = Admin::factory()->create()->assignRole('finance');

        $this->assertEqualsCanonicalizing(['manage-members', 'manage-kyc'], $support->getAllPermissions()->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['manage-sales', 'manage-withdrawals', 'view-reports'], $finance->getAllPermissions()->pluck('name')->all());
    }
}
