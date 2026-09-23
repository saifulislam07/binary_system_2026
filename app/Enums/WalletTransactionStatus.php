<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum WalletTransactionStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Completed = 'completed';
    case Voided = 'voided';
}
