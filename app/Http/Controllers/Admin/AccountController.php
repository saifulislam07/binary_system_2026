<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\AdminAccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/**
 * The signed-in admin's own account: every admin can change their password.
 */
class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.account.edit', ['admin' => $this->admin($request)]);
    }

    public function updatePassword(Request $request, AdminAccountService $accounts): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password:admin'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $accounts->changeOwnPassword($this->admin($request), $request->string('password')->toString());

        return redirect()->route('admin.account.edit')->with('success', 'Password changed.');
    }

    private function admin(Request $request): Admin
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin, 403);

        return $admin;
    }
}
