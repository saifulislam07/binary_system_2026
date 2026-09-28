<?php

namespace App\Services;

use App\Enums\AdminPermission;
use App\Exceptions\ConfigurationException;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Admin accounts and roles. Every change is logged with the acting admin,
 * and no change may lock the panel out: an admin can't deactivate or
 * re-role themselves, and at least one active admin must keep
 * `manage-admins` after any change.
 */
class AdminAccountService
{
    /** The super-admin role always holds every permission. */
    public const SUPER_ROLE = 'admin';

    /**
     * @param  array{name: string, email: string, password: string, role: string}  $data
     */
    public function create(array $data, Admin $by): Admin
    {
        return DB::transaction(function () use ($data, $by) {
            $admin = Admin::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_active' => true,
            ]);
            $admin->syncRoles([$this->role($data['role'])]);

            activity('admins')
                ->performedOn($admin)
                ->causedBy($by)
                ->withProperties(['attributes' => ['name' => $admin->name, 'email' => $admin->email, 'role' => $data['role']]])
                ->log('Admin account created');

            return $admin;
        });
    }

    /**
     * @param  array{name: string, email: string, role: string, is_active: bool, password: string|null}  $data
     */
    public function update(Admin $target, array $data, Admin $by): Admin
    {
        return DB::transaction(function () use ($target, $data, $by) {
            $target = Admin::query()->lockForUpdate()->findOrFail($target->id);
            $before = $this->snapshot($target);

            if ($target->is($by) && (! $data['is_active'] || $data['role'] !== $before['role'])) {
                throw new ConfigurationException('You can’t deactivate yourself or change your own role — ask another admin.');
            }

            $target->forceFill(['name' => $data['name'], 'email' => $data['email'], 'is_active' => $data['is_active']]);

            if (filled($data['password'])) {
                $target->forceFill(['password' => $data['password'], 'remember_token' => null]);
            }

            $target->save();
            $target->syncRoles([$this->role($data['role'])]);
            $this->assertAdminManagersRemain();

            $after = $this->snapshot($target);
            $changed = array_keys(array_diff_assoc($after, $before));

            if ($changed !== [] || filled($data['password'])) {
                activity('admins')
                    ->performedOn($target)
                    ->causedBy($by)
                    ->withProperties([
                        'old' => array_intersect_key($before, array_flip($changed)),
                        'attributes' => array_intersect_key($after, array_flip($changed)),
                        'password_reset' => filled($data['password']),
                    ])
                    ->log('Admin account updated');
            }

            return $target;
        });
    }

    public function changeOwnPassword(Admin $admin, string $password): void
    {
        $admin->forceFill(['password' => $password])->save();

        activity('admins')->performedOn($admin)->causedBy($admin)->log('Admin changed own password');
    }

    /**
     * @param  list<string>  $permissions
     */
    public function createRole(string $name, array $permissions, Admin $by): Role
    {
        return DB::transaction(function () use ($name, $permissions, $by) {
            $role = Role::query()->create(['name' => $name, 'guard_name' => 'admin']); // name uniqueness is validated by the request
            $role->syncPermissions($this->validPermissions($permissions));

            activity('admins')
                ->causedBy($by)
                ->withProperties(['role' => $name, 'permissions' => $this->permissionsOf($role)])
                ->log('Admin role created');

            return $role;
        });
    }

    /**
     * @param  list<string>  $permissions
     */
    public function updateRole(Role $role, array $permissions, Admin $by): Role
    {
        if ($role->name === self::SUPER_ROLE) {
            throw new ConfigurationException('The admin role always has every permission.');
        }

        return DB::transaction(function () use ($role, $permissions, $by) {
            $before = $this->permissionsOf($role);
            $role->syncPermissions($this->validPermissions($permissions));
            $this->assertAdminManagersRemain();
            $after = $this->permissionsOf($role);

            if ($before !== $after) {
                activity('admins')
                    ->causedBy($by)
                    ->withProperties(['role' => $role->name, 'old' => $before, 'attributes' => $after])
                    ->log('Admin role permissions changed');
            }

            return $role;
        });
    }

    private function assertAdminManagersRemain(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $managers = Admin::query()
            ->where('is_active', true)
            ->permission(AdminPermission::ManageAdmins->value)
            ->count();

        if ($managers === 0) {
            throw new ConfigurationException('At least one active admin must keep the “manage admins” permission.');
        }
    }

    private function role(string $name): Role
    {
        $role = Role::query()->where('guard_name', 'admin')->where('name', $name)->first();

        return $role ?? throw new ConfigurationException("Unknown admin role [{$name}].");
    }

    /**
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function validPermissions(array $permissions): array
    {
        return array_values(array_intersect(AdminPermission::values(), $permissions));
    }

    /**
     * @return list<string>
     */
    private function permissionsOf(Role $role): array
    {
        return array_values(Permission::query()
            ->whereHas('roles', fn ($query) => $query->whereKey($role->id))
            ->orderBy('name')
            ->get()
            ->map(fn (Permission $permission): string => $permission->name)
            ->all());
    }

    /**
     * @return array{name: string, email: string, role: string, is_active: string}
     */
    private function snapshot(Admin $admin): array
    {
        return [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => (string) $admin->roles()->value('name'),
            'is_active' => $admin->is_active ? 'yes' : 'no',
        ];
    }
}
