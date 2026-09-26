<?php

namespace App\Notifications;

use App\Models\Sale;
use App\Support\Money;

/**
 * The buyer's purchase went through and its BV is on its way up the tree.
 */
class OrderCompleted extends MemberNotification
{
    public function __construct(public Sale $sale)
    {
        parent::__construct();
    }

    public function kind(): string
    {
        return 'sale';
    }

    public function title(): string
    {
        return 'Purchase complete · ক্রয় সম্পন্ন';
    }

    public function message(object $notifiable): string
    {
        $package = $this->sale->package()->value('name') ?? 'package';

        return 'We received '.Money::format($this->sale->amount)." for the {$package} package. "
            .'It adds '.Money::format($this->sale->bv_value, symbol: false).' BV to your upline’s team volume.';
    }

    public function path(): ?string
    {
        $number = $this->sale->order()->value('order_number');

        return $number === null ? null : route('orders.show', $number, absolute: false);
    }

    protected function data(): array
    {
        return ['amount' => $this->sale->amount];
    }
}
