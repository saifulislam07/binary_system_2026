<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum TransactionDirection: string
{
    use HasValues;

    case Credit = 'credit';
    case Debit = 'debit';
}
