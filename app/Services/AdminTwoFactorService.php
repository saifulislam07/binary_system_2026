<?php

namespace App\Services;

use App\Exceptions\ConfigurationException;
use App\Models\Admin;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Admin two-factor sign-in with an authenticator app (TOTP), plus one-time
 * recovery codes. The only place admin two-factor columns change.
 *
 * Setup is two steps: begin() stores a fresh secret (shown as a QR code),
 * confirm() switches it on once the admin proves their app works. Every
 * change is logged with the acting admin.
 */
class AdminTwoFactorService
{
    public const RECOVERY_CODES = 8;

    public function __construct(private Google2FA $engine) {}

    /**
     * Start (or restart) setup with a new secret. Not active until confirmed.
     */
    public function begin(Admin $admin): void
    {
        if ($admin->hasTwoFactorEnabled()) {
            throw new ConfigurationException('Two-factor sign-in is already on. Turn it off first to move it to a new device.');
        }

        $admin->forceFill([
            'two_factor_secret' => $this->engine->generateSecretKey(32),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        activity('admins')->performedOn($admin)->causedBy($admin)->log('Admin started two-factor setup');
    }

    /**
     * Switch two-factor on after checking a code from the admin's app.
     *
     * @return list<string> the recovery codes, to show once
     */
    public function confirm(Admin $admin, string $code): array
    {
        if ($admin->hasTwoFactorEnabled()) {
            throw new ConfigurationException('Two-factor sign-in is already on.');
        }

        if ($admin->two_factor_secret === null || ! $this->verifyCode($admin, $code)) {
            throw new ConfigurationException('That code is not valid. Check the time on your phone and try the newest code.');
        }

        $codes = $this->newRecoveryCodes();

        $admin->forceFill([
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        activity('admins')->performedOn($admin)->causedBy($admin)->log('Admin turned on two-factor sign-in');

        return $codes;
    }

    /**
     * Replace all recovery codes (the old ones stop working).
     *
     * @return list<string>
     */
    public function regenerateRecoveryCodes(Admin $admin): array
    {
        if (! $admin->hasTwoFactorEnabled()) {
            throw new ConfigurationException('Two-factor sign-in is not on.');
        }

        $codes = $this->newRecoveryCodes();
        $admin->forceFill(['two_factor_recovery_codes' => $codes])->save();

        activity('admins')->performedOn($admin)->causedBy($admin)->log('Admin replaced two-factor recovery codes');

        return $codes;
    }

    /**
     * Turn two-factor off — the admin themself, or (for a lost phone) an
     * admin who manages admin accounts.
     */
    public function disable(Admin $admin, Admin $by): void
    {
        $wasOn = $admin->hasTwoFactorEnabled();

        $admin->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $description = match (true) {
            ! $admin->is($by) => "Admin reset two-factor sign-in for {$admin->email}",
            $wasOn => 'Admin turned off two-factor sign-in',
            default => 'Admin cancelled two-factor setup',
        };

        activity('admins')->performedOn($admin)->causedBy($by)->log($description);
    }

    /**
     * Check a sign-in code: an authenticator code, or else a recovery code
     * (which is used up).
     */
    public function verifyLogin(Admin $admin, string $code, string $recoveryCode): bool
    {
        if (! $admin->hasTwoFactorEnabled()) {
            return false;
        }

        if ($code !== '') {
            return $this->verifyCode($admin, $code);
        }

        $recoveryCode = Str::lower(trim($recoveryCode));
        $codes = $admin->two_factor_recovery_codes ?? [];

        foreach ($codes as $i => $stored) {
            if ($recoveryCode !== '' && hash_equals($stored, $recoveryCode)) {
                unset($codes[$i]);
                $admin->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                activity('admins')->performedOn($admin)->causedBy($admin)
                    ->withProperties(['remaining' => count($codes)])
                    ->log('Admin signed in with a two-factor recovery code');

                return true;
            }
        }

        return false;
    }

    /**
     * QR code (inline SVG) for the authenticator app to scan during setup.
     */
    public function qrCodeSvg(Admin $admin): string
    {
        $url = $this->engine->getQRCodeUrl((string) config('app.name'), $admin->email, (string) $admin->two_factor_secret);

        $svg = (new Writer(new ImageRenderer(
            new RendererStyle(192, 1, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(17, 24, 39))),
            new SvgImageBackEnd,
        )))->writeString($url);

        // Drop the XML declaration so it can sit inline in the page.
        return trim(substr($svg, (int) strpos($svg, "\n") + 1));
    }

    /**
     * A TOTP code from the admin's app. Each code works once: the last
     * accepted time slot is remembered per admin, so a code seen over a
     * shoulder can't be replayed within its window.
     */
    private function verifyCode(Admin $admin, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (preg_match('/^\d{6}$/', $code) !== 1) {
            return false;
        }

        $key = "admin-2fa-used:{$admin->id}";
        $slot = $this->engine->verifyKeyNewer((string) $admin->two_factor_secret, $code, Cache::get($key));

        if ($slot === false) {
            return false;
        }

        Cache::put($key, $slot === true ? $this->engine->getTimestamp() : $slot, now()->addMinutes(5));

        return true;
    }

    /**
     * @return list<string>
     */
    private function newRecoveryCodes(): array
    {
        return array_map(
            fn () => Str::lower(Str::random(5).'-'.Str::random(5)),
            range(1, self::RECOVERY_CODES),
        );
    }
}
