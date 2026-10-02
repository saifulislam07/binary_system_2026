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
        return __('Purchase complete');
    }

    public function message(object $notifiable): string
    {
        return __('We received :amount for the :package package. It adds :bv BV to your upline’s team volume.', [
            'amount' => Money::format($this->sale->amount),
            'package' => $this->sale->package()->value('name') ?? '',
            'bv' => Money::format($this->sale->bv_value, symbol: false),
        ]);
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
