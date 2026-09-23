<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum CommissionType: string
{
    use HasValues;

    case Referral = 'referral';
    case Binary = 'binary';
    case Rank = 'rank';
    case Leadership = 'leadership';
    case Sales = 'sales';
    case Performance = 'performance';
}
