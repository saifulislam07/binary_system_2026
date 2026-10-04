<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ConfigurationException;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\AdminTwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The signed-in admin's own two-factor sign-in (under "My account").
 */
class TwoFactorController extends Controller
{
    public function __construct(private AdminTwoFactorService $twoFactor) {}

    public function store(Request $request): RedirectResponse
    {
        try {
            $this->twoFactor->begin($this->admin($request));
        } catch (ConfigurationException $e) {
            return $this->back()->with('error', $e->getMessage());
        }

        return $this->back()->with('success', 'Scan the QR code with your authenticator app, then enter the code it shows.');
    }

    public function confirm(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);

        try {
            $codes = $this->twoFactor->confirm($this->admin($request), $request->string('code')->toString());
        } catch (ConfigurationException $e) {
            return $this->back()->withErrors(['code' => $e->getMessage()]);
        }

        return $this->back()
            ->with('success', 'Two-factor sign-in is on. Save your recovery codes now — they are shown only once.')
            ->with('recoveryCodes', $codes);
    }

    public function recoveryCodes(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password:admin']]);

        try {
            $codes = $this->twoFactor->regenerateRecoveryCodes($this->admin($request));
        } catch (ConfigurationException $e) {
            return $this->back()->with('error', $e->getMessage());
        }

        return $this->back()
            ->with('success', 'New recovery codes made; the old ones no longer work. Save these now — they are shown only once.')
            ->with('recoveryCodes', $codes);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $admin = $this->admin($request);

        // Cancelling an unfinished setup needs no password; turning it off does.
        if ($admin->hasTwoFactorEnabled()) {
            $request->validate(['current_password' => ['required', 'current_password:admin']]);
        }

        $this->twoFactor->disable($admin, $admin);

        return $this->back()->with('success', 'Two-factor sign-in is off.');
    }

    private function back(): RedirectResponse
    {
        return redirect()->route('admin.account.edit');
    }

    private function admin(Request $request): Admin
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin, 403);

        return $admin;
    }
}
