<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum PaymentGateway: string
{
    use HasValues;

    case Bkash = 'bkash';
    case Sslcommerz = 'sslcommerz';
    case Nagad = 'nagad';
}
