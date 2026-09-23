<?php

namespace App\Services;

use App\DTOs\Payments\PaymentResult;
use App\Enums\MemberStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentGateway as GatewayName;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Events\SaleCompleted;
use App\Exceptions\PaymentException;
use App\Models\Member;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

/**
 * Applies a verified gateway result to its order. Safe to call any number
 * of times for the same payment (browser redirect + IPN + retries).
 */
class OrderPaymentService
{
    public function __construct(private PlacementService $placement) {}

    public function handle(GatewayName $gateway, PaymentResult $result): Order
    {
        $payment = $this->findPayment($gateway, $result);

        return DB::transaction(function () use ($payment, $result) {
            $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($order->status === OrderStatus::Paid || $order->status === OrderStatus::Refunded) {
                // Already settled (duplicate callback, IPN after redirect, late failure): nothing to do.
                return $order;
            }

            if (! $result->isSuccessful()) {
                $this->recordFailure($order, $payment, $result);

                return $order;
            }

            if ($result->amount !== $order->amount) {
                $this->recordAmountMismatch($order, $payment, $result);

                return $order;
            }

            return $this->markPaid($order, $payment, $result);
        });
    }

    /**
     * Money was captured for the right amount: pay the order, activate a
     * pending member, and record the sale. A success is honoured even if an
     * earlier callback marked the order failed/cancelled — the gateway has
     * the customer's money either way.
     */
    private function markPaid(Order $order, Payment $payment, PaymentResult $result): Order
    {
        $order->forceFill(['status' => OrderStatus::Paid, 'paid_at' => now()])->save();

        $payment->forceFill([
            'status' => PaymentStatus::Success,
            'gateway_ref' => $payment->gateway_ref ?? $result->gatewayRef ?? $result->transactionId,
            'raw_response' => [...($payment->raw_response ?? []), 'result' => $result->raw, 'transaction_id' => $result->transactionId],
        ])->save();

        $member = Member::query()->findOrFail($order->member_id);

        if ($member->status === MemberStatus::Pending) {
            // The paid package becomes their joining package; activation assigns code + placement.
            $member->forceFill(['package_id' => $order->package_id])->save();
            $member = $this->placement->activateMember($member);
        }

        $package = $order->package()->firstOrFail();

        $sale = Sale::query()->create([
            'order_id' => $order->id,
            'member_id' => $member->id,
            'package_id' => $package->id,
            'amount' => $order->amount,
            'bv_value' => $package->bv_value,
            'status' => SaleStatus::Completed,
        ]);

        activity('sales')
            ->performedOn($order)
            ->withProperties([
                'sale_id' => $sale->id,
                'amount' => $order->amount,
                'bv_value' => $sale->bv_value,
                'gateway' => $payment->gateway->value,
                'transaction_id' => $result->transactionId,
            ])
            ->log('Order paid');

        // Listeners run synchronously inside this transaction (Phase 5: volume accrual, referral bonus).
        SaleCompleted::dispatch($sale);

        return $order;
    }

    private function recordFailure(Order $order, Payment $payment, PaymentResult $result): void
    {
        if ($payment->status === PaymentStatus::Initiated) {
            $payment->forceFill([
                'status' => $result->status,
                'raw_response' => [...($payment->raw_response ?? []), 'result' => $result->raw, 'message' => $result->message],
            ])->save();
        }

        if ($order->status === OrderStatus::Pending) {
            $order->forceFill([
                'status' => $result->status === PaymentStatus::Cancelled ? OrderStatus::Cancelled : OrderStatus::Failed,
            ])->save();
        }
    }

    /**
     * The gateway captured a different amount than we charged. Don't
     * activate anything; leave a loud audit trail for an admin to resolve.
     */
    private function recordAmountMismatch(Order $order, Payment $payment, PaymentResult $result): void
    {
        $payment->forceFill([
            'status' => PaymentStatus::Failed,
            'raw_response' => [
                ...($payment->raw_response ?? []),
                'result' => $result->raw,
                'error' => 'amount_mismatch',
                'expected' => $order->amount,
                'captured' => $result->amount,
            ],
        ])->save();

        activity('payments')
            ->performedOn($order)
            ->withProperties(['expected' => $order->amount, 'captured' => $result->amount, 'transaction_id' => $result->transactionId])
            ->log('Payment amount mismatch — needs admin review');
    }

    private function findPayment(GatewayName $gateway, PaymentResult $result): Payment
    {
        if ($result->gatewayRef !== null) {
            $payment = Payment::query()->where('gateway', $gateway)->where('gateway_ref', $result->gatewayRef)->first();

            if ($payment !== null) {
                return $payment;
            }
        }

        if ($result->orderNumber !== null) {
            $payment = Payment::query()
                ->where('gateway', $gateway)
                ->whereHas('order', fn ($q) => $q->where('order_number', $result->orderNumber))
                ->latest('id')
                ->first();

            if ($payment !== null) {
                return $payment;
            }
        }

        throw new PaymentException("No {$gateway->value} payment matches this callback.");
    }
}
