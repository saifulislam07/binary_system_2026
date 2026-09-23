<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum WithdrawalMethodType: string
{
    use HasValues;

    case Bank = 'bank';
    case MobileBanking = 'mobile_banking';
}
