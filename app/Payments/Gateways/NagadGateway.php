<?php

namespace App\Payments\Gateways;

use App\DTOs\Payments\PaymentInitiation;
use App\DTOs\Payments\PaymentResult;
use App\Enums\PaymentGateway as GatewayName;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Order;
use App\Payments\Contracts\PaymentGateway;
use App\Support\Money;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Nagad Online Payment API (v-0.2.0).
 * initialize (RSA-encrypted + signed) → complete → customer pays on
 * callBackUrl → callback (?payment_ref_id&status) → verify (server-to-server).
 *
 * Keys: NAGAD_PUBLIC_KEY is Nagad's (PG) public key, used to encrypt what we
 * send. NAGAD_PRIVATE_KEY is our merchant private key, used to sign requests
 * and decrypt Nagad's responses. Both may be bare base64 or full PEM.
 */
class NagadGateway implements PaymentGateway
{
    public function name(): GatewayName
    {
        return GatewayName::Nagad;
    }

    public function initiate(Order $order): PaymentInitiation
    {
        $merchantId = (string) config('services.nagad.merchant_id');
        $orderId = self::nagadOrderId($order->order_number);
        $dateTime = now('Asia/Dhaka')->format('YmdHis');
        $challenge = bin2hex(random_bytes(20));

        $init = $this->http()->post("/check-out/initialize/{$merchantId}/{$orderId}", [
            'accountNumber' => config('services.nagad.merchant_number'),
            'dateTime' => $dateTime,
            ...$this->sealed([
                'merchantId' => $merchantId,
                'datetime' => $dateTime,
                'orderId' => $orderId,
                'challenge' => $challenge,
            ]),
        ])->json() ?? [];

        if (empty($init['sensitiveData'])) {
            throw new PaymentException('Nagad initialize failed: '.($init['message'] ?? 'unknown error'));
        }

        $session = $this->open((string) $init['sensitiveData']);
        $paymentRef = $session['paymentReferenceId'] ?? throw new PaymentException('Nagad initialize returned no paymentReferenceId.');

        $complete = $this->http()->post("/check-out/complete/{$paymentRef}", [
            ...$this->sealed([
                'merchantId' => $merchantId,
                'orderId' => $orderId,
                'currencyCode' => '050', // BDT
                'amount' => Money::toDecimalString($order->amount),
                'challenge' => $session['challenge'] ?? $challenge,
            ]),
            'merchantCallbackURL' => route('payments.callback', GatewayName::Nagad->value),
            'additionalMerchantInfo' => ['orderNumber' => $order->order_number],
        ])->json() ?? [];

        if (($complete['status'] ?? null) !== 'Success' || empty($complete['callBackUrl'])) {
            throw new PaymentException('Nagad complete failed: '.($complete['message'] ?? 'unknown error'));
        }

        return new PaymentInitiation($complete['callBackUrl'], $paymentRef, $complete);
    }

    public function handleCallback(Request $request): PaymentResult
    {
        $paymentRef = $request->string('payment_ref_id')->toString();
        $status = $request->string('status')->toString();

        if ($paymentRef === '') {
            throw new PaymentException('Nagad callback is missing payment_ref_id.');
        }

        if ($status !== 'Success') {
            return new PaymentResult(
                status: in_array($status, ['Aborted', 'Cancelled'], true) ? PaymentStatus::Cancelled : PaymentStatus::Failed,
                gatewayRef: $paymentRef,
                orderNumber: null,
                raw: $request->query(),
                message: $request->string('message')->toString() ?: "Nagad reported status [{$status}].",
            );
        }

        $verification = $this->http()->get("/verify/payment/{$paymentRef}")->json() ?? [];

        if (($verification['status'] ?? null) !== 'Success' || ! isset($verification['amount'])) {
            return new PaymentResult(
                status: PaymentStatus::Failed,
                gatewayRef: $paymentRef,
                orderNumber: null,
                raw: $verification,
                message: $verification['message'] ?? 'Nagad verification failed.',
            );
        }

        return new PaymentResult(
            status: PaymentStatus::Success,
            gatewayRef: $paymentRef,
            orderNumber: null,
            amount: Money::fromTaka((string) $verification['amount']),
            transactionId: $verification['issuerPaymentRefNo'] ?? null,
            raw: $verification,
        );
    }

    /**
     * Nagad order ids must be alphanumeric (≤ 20 chars): ORD-260925-AB12CD34 → ORD260925AB12CD34.
     */
    public static function nagadOrderId(string $orderNumber): string
    {
        return substr((string) preg_replace('/[^A-Za-z0-9]/', '', $orderNumber), 0, 20);
    }

    /**
     * @param  array<string, string>  $payload
     * @return array{sensitiveData: string, signature: string}
     */
    private function sealed(array $payload): array
    {
        $json = (string) json_encode($payload);

        if (! openssl_public_encrypt($json, $encrypted, $this->key('public_key', 'PUBLIC'))) {
            throw new PaymentException('Could not encrypt the Nagad payload (check NAGAD_PUBLIC_KEY).');
        }

        if (! openssl_sign($json, $signature, $this->key('private_key', 'PRIVATE'), OPENSSL_ALGO_SHA256)) {
            throw new PaymentException('Could not sign the Nagad payload (check NAGAD_PRIVATE_KEY).');
        }

        return ['sensitiveData' => base64_encode($encrypted), 'signature' => base64_encode($signature)];
    }

    /**
     * @return array<string, mixed>
     */
    private function open(string $sensitiveData): array
    {
        if (! openssl_private_decrypt((string) base64_decode($sensitiveData, true), $decrypted, $this->key('private_key', 'PRIVATE'))) {
            throw new PaymentException('Could not decrypt the Nagad response (check NAGAD_PRIVATE_KEY).');
        }

        return json_decode($decrypted, true) ?: [];
    }

    private function key(string $configKey, string $type): string
    {
        $key = trim((string) config("services.nagad.{$configKey}"));

        if ($key === '') {
            throw new PaymentException("Nagad {$configKey} is not configured.");
        }

        return str_contains($key, '-----BEGIN')
            ? $key
            : "-----BEGIN {$type} KEY-----\n".chunk_split($key, 64, "\n")."-----END {$type} KEY-----\n";
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.nagad.base_url'), '/'))
            ->timeout((int) config('payments.http_timeout'))
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'X-KM-Api-Version' => 'v-0.2.0',
                'X-KM-IP-V4' => request()->ip() ?? '127.0.0.1',
                'X-KM-Client-Type' => 'PC_WEB',
            ]);
    }
}
