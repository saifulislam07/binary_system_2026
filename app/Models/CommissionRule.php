<?php

namespace App\Models;

use Database\Factories\CommissionRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Admin-editable commission config. Rates are basis points, caps are poysha.
 */
#[Fillable(['key', 'value', 'description'])]
class CommissionRule extends Model
{
    /** @use HasFactory<CommissionRuleFactory> */
    use HasFactory;

    // Keys (seeded by ReferenceDataSeeder).
    public const BINARY_RATE_BPS = 'binary_commission_rate_bps';

    public const REFERRAL_RATE_BPS = 'referral_rate_bps';

    public const DAILY_CAP = 'daily_cap';

    public const WEEKLY_CAP = 'weekly_cap';

    public const MONTHLY_CAP = 'monthly_cap';

    public const CARRY_FORWARD_ENABLED = 'carry_forward_enabled';

    public const CAP_OVERFLOW_BEHAVIOR = 'cap_overflow_behavior'; // 'void' | 'carry_forward'

    public static function raw(string $key): string
    {
        $value = static::query()->where('key', $key)->value('value');

        if ($value === null) {
            throw new RuntimeException("Commission rule [{$key}] is not configured.");
        }

        return (string) $value;
    }

    public static function int(string $key): int
    {
        return (int) static::raw($key);
    }

    public static function bool(string $key): bool
    {
        return filter_var(static::raw($key), FILTER_VALIDATE_BOOLEAN);
    }
}
