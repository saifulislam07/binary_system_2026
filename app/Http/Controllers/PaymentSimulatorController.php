<?php

namespace App\Http\Controllers;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Contracts\View\View;

/**
 * The "gateway-hosted" page for the dev-only payment simulator.
 */
class PaymentSimulatorController extends Controller
{
    public function __invoke(string $ref): View
    {
        abort_unless(config('payments.simulator') && ! app()->isProduction(), 404);

        $payment = Payment::query()
            ->with('order.package')
            ->where('gateway', PaymentGateway::Simulator)
            ->where('gateway_ref', $ref)
            ->firstOrFail();

        return view('payments.simulator', [
            'payment' => $payment,
            'outcomes' => [
                PaymentStatus::Success->value => 'Pay successfully',
                PaymentStatus::Failed->value => 'Simulate failure',
                PaymentStatus::Cancelled->value => 'Cancel',
            ],
        ]);
    }
}
