<?php

namespace App\Models;

use Database\Factories\SettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * General app settings (key/value). Money values are poysha.
 */
#[Fillable(['key', 'value', 'description'])]
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    public const MIN_WITHDRAWAL = 'min_withdrawal';

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = static::get($key);

        return $value === null ? $default : (int) $value;
    }
}
