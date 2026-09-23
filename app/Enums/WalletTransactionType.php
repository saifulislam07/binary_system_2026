<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum WalletTransactionType: string
{
    use HasValues;

    case ReferralBonus = 'referral_bonus';
    case BinaryCommission = 'binary_commission';
    case RankBonus = 'rank_bonus';
    case SalesBonus = 'sales_bonus';
    case LeadershipBonus = 'leadership_bonus';
    case PerformanceBonus = 'performance_bonus';
    case Withdrawal = 'withdrawal';
    case Adjustment = 'adjustment';
    case Refund = 'refund';
    case Reversal = 'reversal';

    /**
     * Bilingual label for member-facing screens.
     */
    public function label(): string
    {
        return match ($this) {
            self::ReferralBonus => 'Referral bonus · রেফারেল বোনাস',
            self::BinaryCommission => 'Binary commission · বাইনারি কমিশন',
            self::RankBonus => 'Rank bonus · র‍্যাংক বোনাস',
            self::SalesBonus => 'Sales bonus · সেলস বোনাস',
            self::LeadershipBonus => 'Leadership bonus · লিডারশিপ বোনাস',
            self::PerformanceBonus => 'Performance bonus · পারফরম্যান্স বোনাস',
            self::Withdrawal => 'Withdrawal · উত্তোলন',
            self::Adjustment => 'Adjustment · সমন্বয়',
            self::Refund => 'Refund · ফেরত',
            self::Reversal => 'Reversal · বাতিল',
        };
    }

    /**
     * Types that count as earned income.
     *
     * @return list<self>
     */
    public static function income(): array
    {
        return [
            self::ReferralBonus, self::BinaryCommission, self::RankBonus,
            self::SalesBonus, self::LeadershipBonus, self::PerformanceBonus,
        ];
    }
}
