<?php

namespace App\Services;

use App\DTOs\Payments\PaymentInitiation;
use App\Enums\MemberStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentGateway as GatewayName;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Member;
use App\Models\Order;
use App\Models\Package;
use App\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutService
{
    public function __construct(private PaymentGatewayManager $gateways) {}

    /**
     * Create a pending order + payment and hand the order to the gateway.
     *
     * @return array{0: Order, 1: PaymentInitiation}
     */
    public function checkout(Member $member, Package $package, GatewayName $gatewayName): array
    {
        if ($member->status === MemberStatus::Suspended) {
            throw new PaymentException('Suspended members cannot place orders.');
        }

        if (! $package->is_active) {
            throw new PaymentException('This package is not available.');
        }

        if (! $this->gateways->isAvailable($gatewayName)) {
            throw new PaymentException("Payment method [{$gatewayName->value}] is not available.");
        }

        [$order, $payment] = DB::transaction(function () use ($member, $package, $gatewayName) {
            $order = Order::query()->create([
                'order_number' => $this->newOrderNumber(),
                'member_id' => $member->id,
                'package_id' => $package->id,
                'amount' => $package->price,
                'status' => OrderStatus::Pending,
            ]);

            $order->items()->create([
                'package_id' => $package->id,
                'quantity' => 1,
                'unit_price' => $package->price,
                'total' => $package->price,
            ]);

            $payment = $order->payments()->create([
                'gateway' => $gatewayName,
                'amount' => $order->amount,
                'status' => PaymentStatus::Initiated,
            ]);

            return [$order, $payment];
        });

        // Talk to the gateway outside the transaction — never hold DB locks over HTTP.
        try {
            $initiation = $this->gateways->gateway($gatewayName)->initiate($order);
        } catch (\Throwable $e) {
            $payment->update(['status' => PaymentStatus::Failed, 'raw_response' => ['error' => $e->getMessage()]]);
            $order->update(['status' => OrderStatus::Failed]);

            throw $e instanceof PaymentException ? $e : new PaymentException('Could not start the payment. Please try again.', previous: $e);
        }

        $payment->update([
            'gateway_ref' => $initiation->gatewayRef,
            'raw_response' => ['initiation' => $initiation->raw],
        ]);

        return [$order, $initiation];
    }

    private function newOrderNumber(): string
    {
        return 'ORD-'.now()->format('ymd').'-'.Str::upper(Str::random(8));
    }
}
