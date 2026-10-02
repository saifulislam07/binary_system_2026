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
     * Label in the current language (member pages translate it to Bangla).
     */
    public function label(): string
    {
        return match ($this) {
            self::ReferralBonus => __('Referral bonus'),
            self::BinaryCommission => __('Binary commission'),
            self::RankBonus => __('Rank bonus'),
            self::SalesBonus => __('Sales bonus'),
            self::LeadershipBonus => __('Leadership bonus'),
            self::PerformanceBonus => __('Performance bonus'),
            self::Withdrawal => __('Withdrawal'),
            self::Adjustment => __('Adjustment'),
            self::Refund => __('Refund'),
            self::Reversal => __('Reversal'),
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
