<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum BonusType: string
{
    use HasValues;

    case Sponsor = 'sponsor';
    case Binary = 'binary';
    case Rank = 'rank';
    case Leadership = 'leadership';
    case Sales = 'sales';
    case Performance = 'performance';
}
