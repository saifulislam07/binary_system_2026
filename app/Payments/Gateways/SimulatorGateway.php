<?php

namespace App\Payments\Gateways;

use App\DTOs\Payments\PaymentInitiation;
use App\DTOs\Payments\PaymentResult;
use App\Enums\PaymentGateway as GatewayName;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\Contracts\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Local click-through gateway for development and demos: a page with
 * "Pay", "Fail" and "Cancel" buttons. Refuses to run unless
 * config('payments.simulator') is on (never in production).
 */
class SimulatorGateway implements PaymentGateway
{
    public function name(): GatewayName
    {
        return GatewayName::Simulator;
    }

    public function initiate(Order $order): PaymentInitiation
    {
        $this->ensureEnabled();

        $ref = 'SIM-'.Str::upper(Str::random(16));

        return new PaymentInitiation(route('payments.simulator.show', ['ref' => $ref]), $ref, ['simulated' => true]);
    }

    public function handleCallback(Request $request): PaymentResult
    {
        $this->ensureEnabled();

        $ref = $request->string('ref')->toString();
        $status = PaymentStatus::tryFrom($request->string('status')->toString()) ?? PaymentStatus::Failed;

        $payment = Payment::query()
            ->with('order')
            ->where('gateway', GatewayName::Simulator)
            ->where('gateway_ref', $ref)
            ->first() ?? throw new PaymentException("Unknown simulator payment [{$ref}].");

        return new PaymentResult(
            status: $status,
            gatewayRef: $ref,
            orderNumber: $payment->order?->order_number,
            amount: $status === PaymentStatus::Success ? $payment->amount : null,
            transactionId: $status === PaymentStatus::Success ? 'SIMTRX-'.Str::upper(Str::random(10)) : null,
            raw: ['simulated' => true, 'status' => $status->value],
        );
    }

    private function ensureEnabled(): void
    {
        if (! config('payments.simulator') || app()->isProduction()) {
            throw new PaymentException('The payment simulator is disabled.');
        }
    }
}
