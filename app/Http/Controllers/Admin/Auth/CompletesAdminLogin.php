<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Models\Admin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The last step of an admin sign-in, shared by the password form and the
 * two-factor challenge: start the session, record it, go on.
 */
trait CompletesAdminLogin
{
    protected function completeLogin(Request $request, Admin $admin, bool $remember): RedirectResponse
    {
        Auth::guard('admin')->login($admin, $remember);
        $request->session()->regenerate();

        $admin->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        activity('admin-auth')->causedBy($admin)->log('Admin logged in');

        return redirect()->intended(route('admin.dashboard'));
    }
}
