<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum KycDocumentType: string
{
    use HasValues;

    case Nid = 'nid';
    case Passport = 'passport';
}
