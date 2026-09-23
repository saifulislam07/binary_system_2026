<?php

namespace App\DTOs\Payments;

final readonly class PaymentInitiation
{
    /**
     * @param  string  $redirectUrl  gateway-hosted payment page to send the customer to
     * @param  string|null  $gatewayRef  the gateway's id for this payment (bKash paymentID, SSLCommerz sessionkey, Nagad paymentReferenceId)
     * @param  array<string, mixed>  $raw  gateway response, stored on the payment for audit
     */
    public function __construct(
        public string $redirectUrl,
        public ?string $gatewayRef,
        public array $raw = [],
    ) {}
}
