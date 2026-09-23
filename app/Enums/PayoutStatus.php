<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum PayoutStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Paid = 'paid';
    case Voided = 'voided';
    case Reversed = 'reversed';
}
