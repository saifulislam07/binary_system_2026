<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\SaleStatus;
use App\Exceptions\RefundException;
use App\Jobs\ReverseCommissionForSale;
use App\Models\Admin;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

/**
 * Marks a sale refunded and queues the reversal of everything it generated
 * (BV up the tree, commissions, wallet credits). Returning the customer's
 * money through the gateway is done by an admin outside the system.
 */
class RefundService
{
    public function refund(Sale $sale, string $reason, ?Admin $by = null): Refund
    {
        return DB::transaction(function () use ($sale, $reason, $by) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            if ($sale->status === SaleStatus::Refunded || $sale->refund()->exists()) {
                throw new RefundException("Sale [{$sale->id}] has already been refunded.");
            }

            $sale->forceFill(['status' => SaleStatus::Refunded, 'refunded_at' => now()])->save();

            Order::query()->whereKey($sale->order_id)->update(['status' => OrderStatus::Refunded]);

            $refund = $sale->refund()->create([
                'amount' => $sale->amount,
                'reason' => $reason,
                'processed_by' => $by?->id,
                'processed_at' => now(),
            ]);

            activity('sales')
                ->performedOn($sale)
                ->causedBy($by)
                ->withProperties(['refund_id' => $refund->id, 'amount' => $sale->amount, 'reason' => $reason])
                ->log('Sale refunded');

            ReverseCommissionForSale::dispatch($sale->id)->afterCommit();

            return $refund;
        });
    }
}
