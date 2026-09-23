<?php

namespace App\Payments\Contracts;

use App\DTOs\Payments\PaymentInitiation;
use App\DTOs\Payments\PaymentResult;
use App\Enums\PaymentGateway as GatewayName;
use App\Models\Order;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function name(): GatewayName;

    /**
     * Start a payment for the order with the gateway and return where to send
     * the customer. Throws PaymentException if the gateway refuses.
     */
    public function initiate(Order $order): PaymentInitiation;

    /**
     * Turn the gateway's browser redirect / IPN into a verified result.
     *
     * Implementations MUST confirm the outcome server-to-server (execute /
     * validate / verify API) and report the amount the gateway actually
     * captured — query-string or POST data alone is never trusted.
     */
    public function handleCallback(Request $request): PaymentResult;
}
