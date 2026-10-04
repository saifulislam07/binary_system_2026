<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\AdminAccountService;
use App\Services\AdminTwoFactorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/**
 * The signed-in admin's own account: every admin can change their password
 * and manage their two-factor sign-in (TwoFactorController).
 */
class AccountController extends Controller
{
    public function edit(Request $request, AdminTwoFactorService $twoFactor): View
    {
        $admin = $this->admin($request);
        $settingUp = $admin->two_factor_secret !== null && ! $admin->hasTwoFactorEnabled();

        return view('admin.account.edit', [
            'admin' => $admin,
            'settingUp' => $settingUp,
            'qrCode' => $settingUp ? $twoFactor->qrCodeSvg($admin) : null,
            'required' => (bool) config('business.admin_two_factor_required'),
        ]);
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
