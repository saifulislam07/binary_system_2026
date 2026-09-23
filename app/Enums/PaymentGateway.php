<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum PaymentGateway: string
{
    use HasValues;

    case Bkash = 'bkash';
    case Sslcommerz = 'sslcommerz';
    case Nagad = 'nagad';
    case Simulator = 'simulator';

    public function label(): string
    {
        return match ($this) {
            self::Bkash => 'bKash',
            self::Sslcommerz => 'Card / Internet banking (SSLCommerz)',
            self::Nagad => 'Nagad',
            self::Simulator => 'Payment simulator (dev only)',
        };
    }
}
