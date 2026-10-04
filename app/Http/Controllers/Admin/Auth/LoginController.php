<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin login on its own session guard (`admin`), separate from members.
 * Admins with two-factor on are sent to the code challenge before any
 * session starts (see TwoFactorChallengeController).
 */
class LoginController extends Controller
{
    use CompletesAdminLogin;

    public function create(): View
    {
        return view('admin.auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $admin = $request->validateCredentials();

        if ($admin->hasTwoFactorEnabled()) {
            TwoFactorChallengeController::remember($request, $admin, $request->boolean('remember'));

            return redirect()->route('admin.two-factor.challenge');
        }

        return $this->completeLogin($request, $admin, $request->boolean('remember'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
