<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\AdminTwoFactorService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Second sign-in step for admins with two-factor on. The password step
 * leaves only the admin's id in the session (no login yet); a valid
 * authenticator or recovery code here completes the sign-in.
 */
class TwoFactorChallengeController extends Controller
{
    use CompletesAdminLogin;

    private const SESSION_KEY = 'admin.two_factor';

    /** Minutes the password step stays valid. */
    private const EXPIRES_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public static function remember(Request $request, Admin $admin, bool $remember): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'id' => $admin->id,
            'remember' => $remember,
            'expires' => now()->addMinutes(self::EXPIRES_MINUTES)->getTimestamp(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if ($this->pendingAdmin($request) === null) {
            return $this->restart();
        }

        return view('admin.auth.two-factor-challenge');
    }

    public function store(Request $request, AdminTwoFactorService $twoFactor): RedirectResponse
    {
        $admin = $this->pendingAdmin($request);

        if ($admin === null) {
            return $this->restart();
        }

        $request->validate([
            'code' => ['nullable', 'string', 'max:20', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string', 'max:30'],
        ]);

        $throttleKey = 'admin-2fa|'.$admin->id;

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'code' => trans('auth.throttle', ['seconds' => $seconds = RateLimiter::availableIn($throttleKey), 'minutes' => ceil($seconds / 60)]),
            ]);
        }

        if (! $twoFactor->verifyLogin($admin, (string) $request->input('code', ''), (string) $request->input('recovery_code', ''))) {
            RateLimiter::hit($throttleKey);
            event(new Failed('admin', $admin, ['email' => $admin->email]));

            throw ValidationException::withMessages([
                'code' => 'That code is not valid.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $remember = (bool) $request->session()->pull(self::SESSION_KEY.'.remember', false);
        $request->session()->forget(self::SESSION_KEY);

        return $this->completeLogin($request, $admin, $remember);
    }

    /**
     * The admin who passed the password step, if that was recent and they
     * can still sign in.
     */
    private function pendingAdmin(Request $request): ?Admin
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! is_array($pending) || ($pending['expires'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        $admin = Admin::query()->where('is_active', true)->find($pending['id'] ?? null);

        return $admin instanceof Admin && $admin->hasTwoFactorEnabled() ? $admin : null;
    }

    private function restart(): RedirectResponse
    {
        session()->forget(self::SESSION_KEY);

        return redirect()->route('admin.login')->withErrors(['email' => 'Please sign in again.']);
    }
}
