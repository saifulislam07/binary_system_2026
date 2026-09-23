<?php

namespace App\Jobs;

use App\Models\Sale;
use App\Services\CommissionReversalService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Undo everything a refunded sale generated. Queued by RefundService after
 * the refund commits. Safe to retry: the reversal is idempotent.
 */
class ReverseCommissionForSale implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $saleId) {}

    public function handle(CommissionReversalService $reversal): void
    {
        $reversal->reverseSale(Sale::query()->findOrFail($this->saleId));
    }
}
