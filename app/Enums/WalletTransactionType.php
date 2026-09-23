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
}
