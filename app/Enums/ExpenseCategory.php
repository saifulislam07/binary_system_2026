<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;

enum ExpenseCategory: string
{
    use HasValues;

    case ProductCost = 'product_cost';
    case Commission = 'commission';
    case Delivery = 'delivery';
    case Marketing = 'marketing';
    case Salary = 'salary';
    case Server = 'server';
    case GatewayFees = 'gateway_fees';
    case Other = 'other';
}
