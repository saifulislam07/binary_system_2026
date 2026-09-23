<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum CommissionCycleStatus: string
{
    use HasValues;

    case Running = 'running';
    case Closed = 'closed';
}
