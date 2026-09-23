<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum SaleStatus: string
{
    use HasValues;

    case Completed = 'completed';
    case Refunded = 'refunded';
}
