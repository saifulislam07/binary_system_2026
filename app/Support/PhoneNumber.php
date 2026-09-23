<?php

namespace App\Support;

/**
 * Bangladeshi mobile numbers, stored in E.164: +8801XXXXXXXXX.
 */
final class PhoneNumber
{
    public const PATTERN = '/^\+8801[3-9]\d{8}$/';

    /**
     * "01712-345678", "8801712345678", "+880 1712 345678" → "+8801712345678".
     * Returns the cleaned input unchanged if it can't be recognised, so
     * validation can reject it with PATTERN.
     */
    public static function normalize(?string $input): string
    {
        $digits = preg_replace('/\D+/', '', (string) $input) ?? '';

        return match (true) {
            str_starts_with($digits, '8801') && strlen($digits) === 13 => '+'.$digits,
            str_starts_with($digits, '01') && strlen($digits) === 11 => '+88'.$digits,
            default => $digits,
        };
    }

    public static function isValid(string $normalized): bool
    {
        return preg_match(self::PATTERN, $normalized) === 1;
    }
}
