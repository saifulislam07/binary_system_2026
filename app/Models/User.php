<?php

namespace App\Models;

use App\Http\Middleware\SetLocale;
use App\Notifications\ResetPasswordLink;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Member-side authenticatable (guard: web).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string|null $locale
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $ip_registered
 * @property string|null $device_registered
 * @property bool $is_active
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'phone', 'password', 'ip_registered', 'device_registered'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected string $guard_name = 'web';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Notifications and mail go out in the member's chosen language.
     */
    public function preferredLocale(): string
    {
        return $this->locale !== null && SetLocale::supports($this->locale)
            ? $this->locale
            : (string) config('business.default_locale');
    }

    /** @return HasOne<Member, $this> */
    public function member(): HasOne
    {
        return $this->hasOne(Member::class);
    }

    /**
     * Fortify's forgot-password flow: queued, through our EmailChannel.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordLink($token));
    }

    public function routeNotificationForSms(): ?string
    {
        return $this->phone;
    }

    public function routeNotificationForWhatsapp(): ?string
    {
        return $this->phone;
    }
}
