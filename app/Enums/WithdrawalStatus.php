<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum WithdrawalStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Approved = 'approved';
    case Processing = 'processing';
    case Paid = 'paid';
    case Rejected = 'rejected';
}
