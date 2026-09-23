<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Integer money helpers. All amounts are poysha (1 BDT = 100 poysha) and all
 * rates are basis points (10000 = 100%). Never use floats for money.
 */
final class Money
{
    public const SCALE = 100;

    public const BPS_DENOMINATOR = 10_000;

    /**
     * `percentOf(150_000, 1000)` → 15_000 (10% of ৳1,500 = ৳150).
     * Rounds down, so the company never pays out a fraction it didn't earn.
     */
    public static function percentOf(int $amount, int $basisPoints): int
    {
        if ($basisPoints < 0) {
            throw new InvalidArgumentException('Rate must not be negative.');
        }

        return intdiv($amount * $basisPoints, self::BPS_DENOMINATOR);
    }

    /**
     * Parse a user-entered taka string ("1,250.50", "৳1000") into poysha.
     */
    public static function fromTaka(string $taka): int
    {
        $clean = str_replace([',', '৳', ' '], '', trim($taka));

        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $clean)) {
            throw new InvalidArgumentException("Invalid taka amount [{$taka}].");
        }

        $negative = str_starts_with($clean, '-');
        [$whole, $fraction] = array_pad(explode('.', ltrim($clean, '-')), 2, '0');
        $poysha = (int) $whole * self::SCALE + (int) str_pad($fraction, 2, '0');

        return $negative ? -$poysha : $poysha;
    }

    /**
     * Format poysha for display: 125050 → "৳1,250.50".
     */
    public static function format(int $poysha, bool $symbol = true): string
    {
        $sign = $poysha < 0 ? '-' : '';
        $abs = abs($poysha);
        $formatted = number_format(intdiv($abs, self::SCALE)).'.'.str_pad((string) ($abs % self::SCALE), 2, '0', STR_PAD_LEFT);

        return $sign.($symbol ? '৳' : '').$formatted;
    }
}
