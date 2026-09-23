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

        Role::findOrCreate('admin', 'admin')->syncPermissions(AdminPermission::values());
        Role::findOrCreate('member', 'web');
    }
}
