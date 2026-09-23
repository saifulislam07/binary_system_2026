<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum MemberStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
}
