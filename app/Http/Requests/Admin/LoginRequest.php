<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public const MAX_ATTEMPTS = 5;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Check the email and password against the admin guard without signing
     * in — the controller signs in, or first asks for a two-factor code.
     * Disabled admins are rejected with the same message as bad credentials.
     *
     * @throws ValidationException
     */
    public function validateCredentials(): Admin
    {
        $this->ensureIsNotRateLimited();

        $credentials = [...$this->only('email', 'password'), 'is_active' => true];
        $provider = Auth::guard('admin')->getProvider();
        $admin = $provider->retrieveByCredentials($credentials);

        if (! $admin instanceof Admin || ! $provider->validateCredentials($admin, $credentials)) {
            RateLimiter::hit($this->throttleKey());
            event(new Failed('admin', $admin, $credentials));

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $provider->rehashPasswordIfRequired($admin, $credentials);
        RateLimiter::clear($this->throttleKey());

        return $admin;
    }

    /**
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    public function throttleKey(): string
    {
        return 'admin-login|'.Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
