<?php

namespace App\Http\Controllers;

use App\Enums\PaymentGateway;
use App\Exceptions\PaymentException;
use App\Payments\PaymentGatewayManager;
use App\Services\OrderPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Browser redirects and server-to-server IPNs from every gateway land here.
 * Not behind `auth` (the gateway's cross-site POST carries no session) and
 * CSRF-exempt; trust comes from the gateway's server-side verification.
 */
class PaymentCallbackController extends Controller
{
    public function __invoke(
        Request $request,
        string $gateway,
        PaymentGatewayManager $gateways,
        OrderPaymentService $payments,
    ): RedirectResponse {
        $name = PaymentGateway::tryFrom($gateway) ?? abort(404);

        try {
            $result = $gateways->gateway($name)->handleCallback($request);
            $order = $payments->handle($name, $result);
        } catch (PaymentException $e) {
            Log::warning('Payment callback rejected', ['gateway' => $name->value, 'error' => $e->getMessage(), 'input' => $request->except(['store_passwd'])]);

            return redirect()->route('checkout.index')->withErrors(['gateway' => __('We could not confirm this payment. If money was deducted, please contact support.')]);
        }

        return redirect()->route('orders.show', $order);
    }
}
