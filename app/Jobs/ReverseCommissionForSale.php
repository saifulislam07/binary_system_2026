<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Undo everything a refunded sale generated. Queued by RefundService after
 * the refund commits.
 */
class ReverseCommissionForSale implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $saleId) {}

    /**
     * TODO(Phase 5): implement with this exact signature, all in ONE DB transaction:
     *
     *   public function handle(TeamVolumeService $volumes, WalletService $wallets): void
     *
     *   1. $sale = Sale::findOrFail($this->saleId); bail if not SaleStatus::Refunded.
     *   2. $volumes->reverseVolume($sale) — subtract the sale's bv_value from
     *      each placement ancestor's side volume (lock nodes top-down, see
     *      PlacementService), clamping unmatched volume at 0.
     *   3. For every commission with source_sale_id = $sale->id (and binary
     *      commissions whose matched volume included it): status → reversed,
     *      and for each paid one write a `reversal` debit via $wallets.
     *   4. Never delete or edit the original rows — reversal entries only.
     *   5. Idempotent: skip commissions already reversed.
     */
    public function handle(): void
    {
        // Intentionally empty until Phase 5 builds the commission engine.
    }
}
