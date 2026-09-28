<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermission;
use App\Exceptions\ConfigurationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveAdminRequest;
use App\Models\Admin;
use App\Services\AdminAccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Admin accounts and roles (manage-admins).
 */
class AdminUserController extends Controller
{
    public function index(): View
    {
        return view('admin.admins.index', [
            'admins' => Admin::query()->with('roles:id,name')->orderByDesc('is_active')->orderBy('name')->get(),
            'roles' => Role::query()->where('guard_name', 'admin')->with('permissions:id,name')->withCount('users')->orderBy('id')->get(),
            'permissions' => AdminPermission::cases(),
        ]);
    }

    public function create(): View
    {
        return view('admin.admins.form', ['target' => new Admin(['is_active' => true]), 'roles' => $this->roleNames(), 'currentRole' => null]);
    }

    public function store(SaveAdminRequest $request, AdminAccountService $accounts): RedirectResponse
    {
        $admin = $accounts->create([
            'name' => $request->string('name')->trim()->toString(),
            'email' => $request->string('email')->trim()->lower()->toString(),
            'password' => $request->string('password')->toString(),
            'role' => $request->string('role')->toString(),
        ], $this->admin($request));

        return redirect()->route('admin.admins.index')->with('success', "Admin account for {$admin->email} created. Share the password privately; they can change it under “My account”.");
    }

    public function edit(Admin $admin): View
    {
        return view('admin.admins.form', ['target' => $admin, 'roles' => $this->roleNames(), 'currentRole' => $admin->roles()->value('name')]);
    }

    public function update(SaveAdminRequest $request, Admin $admin, AdminAccountService $accounts): RedirectResponse
    {
        try {
            $accounts->update($admin, [
                'name' => $request->string('name')->trim()->toString(),
                'email' => $request->string('email')->trim()->lower()->toString(),
                'role' => $request->string('role')->toString(),
                'is_active' => $request->boolean('is_active'),
                'password' => $request->filled('password') ? $request->string('password')->toString() : null,
            ], $this->admin($request));
        } catch (ConfigurationException $e) {
            return back()->withInput($request->except('password', 'password_confirmation'))->with('error', $e->getMessage());
        }

        return redirect()->route('admin.admins.index')->with('success', "Admin {$admin->email} saved.");
    }

    public function storeRole(Request $request, AdminAccountService $accounts): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9-]*$/', Rule::unique('roles', 'name')->where('guard_name', 'admin')],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in(AdminPermission::values())],
        ], ['name.regex' => 'Use lowercase letters, digits and dashes, e.g. "accounts".']);

        $accounts->createRole($data['name'], array_values($data['permissions'] ?? []), $this->admin($request));

        return redirect()->route('admin.admins.index')->with('success', "Role {$data['name']} created.");
    }

    public function updateRole(Request $request, Role $role, AdminAccountService $accounts): RedirectResponse
    {
        abort_unless($role->guard_name === 'admin', 404);

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in(AdminPermission::values())],
        ]);

        try {
            $accounts->updateRole($role, array_values($data['permissions'] ?? []), $this->admin($request));
        } catch (ConfigurationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.admins.index')->with('success', "Permissions for {$role->name} saved.");
    }

    /**
     * @return list<string>
     */
    private function roleNames(): array
    {
        return array_values(Role::query()->where('guard_name', 'admin')->orderBy('id')->get()
            ->map(fn (Role $role): string => $role->name)
            ->all());
    }

    private function admin(Request $request): Admin
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin, 403);

        return $admin;
    }
}
