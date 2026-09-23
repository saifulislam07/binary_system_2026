<?php

namespace App\DTOs\Payments;

use App\Enums\PaymentStatus;

/**
 * Outcome of a gateway callback after server-to-server verification.
 * At least one of $gatewayRef / $orderNumber identifies the payment.
 */
final readonly class PaymentResult
{
    /**
     * @param  int|null  $amount  poysha actually captured by the gateway (null when not successful)
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public PaymentStatus $status,
        public ?string $gatewayRef,
        public ?string $orderNumber,
        public ?int $amount = null,
        public ?string $transactionId = null,
        public array $raw = [],
        public ?string $message = null,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === PaymentStatus::Success;
    }
}
