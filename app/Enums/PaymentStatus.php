<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum PaymentStatus: string
{
    use HasValues;

    case Initiated = 'initiated';
    case Success = 'success';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
