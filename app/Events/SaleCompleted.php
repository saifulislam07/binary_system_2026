<?php

namespace App\Events;

use App\Models\Sale;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A sale was recorded for a paid order. Dispatched *inside* the payment
 * transaction, so synchronous listeners (team volume, referral bonus) either
 * all commit with the sale or all roll back. Queued listeners must use
 * afterCommit.
 */
class SaleCompleted
{
    use Dispatchable;

    public function __construct(public Sale $sale) {}
}
