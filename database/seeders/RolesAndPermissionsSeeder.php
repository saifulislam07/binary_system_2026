<?php

namespace Database\Seeders;

use App\Enums\AdminPermission;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent: safe to run on every deploy.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (AdminPermission::values() as $permission) {
            Permission::findOrCreate($permission, 'admin');
        }

        // findOrCreate() loads the cache before inserting; reload it so
        // syncPermissions() can resolve the permissions just created.
        $registrar->forgetCachedPermissions();

        // Super admin always holds every permission.
        Role::findOrCreate('admin', 'admin')->syncPermissions(AdminPermission::values());
        Role::findOrCreate('member', 'web');

        // Example limited roles. Permissions are only set when the role is
        // first created, so later edits by an admin are never overwritten.
        $limited = [
            'support' => [AdminPermission::ManageMembers, AdminPermission::ManageKyc],
            'finance' => [AdminPermission::ManageSales, AdminPermission::ManageWithdrawals, AdminPermission::ViewReports],
        ];

        foreach ($limited as $name => $permissions) {
            if (! Role::query()->where('name', $name)->where('guard_name', 'admin')->exists()) {
                Role::create(['name' => $name, 'guard_name' => 'admin'])
                    ->syncPermissions(array_map(fn (AdminPermission $p) => $p->value, $permissions));
            }
        }
    }
}
