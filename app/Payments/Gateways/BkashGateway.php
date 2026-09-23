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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * bKash Tokenized Checkout (v1.2.0-beta).
 * grant token → create payment → customer pays on bkashURL → callback
 * (?paymentID&status) → execute payment (server-to-server) → verified.
 */
class BkashGateway implements PaymentGateway
{
    private const SUCCESS_CODE = '0000';

    public function name(): GatewayName
    {
        return GatewayName::Bkash;
    }

    public function initiate(Order $order): PaymentInitiation
    {
        $response = $this->api()->post('/tokenized/checkout/create', [
            'mode' => '0011',
            'payerReference' => $order->member->member_code ?? (string) $order->member_id,
            'callbackURL' => route('payments.callback', GatewayName::Bkash->value),
            'amount' => Money::toDecimalString($order->amount),
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => $order->order_number,
        ])->json();

        if (($response['statusCode'] ?? null) !== self::SUCCESS_CODE || empty($response['bkashURL']) || empty($response['paymentID'])) {
            throw new PaymentException('bKash refused the payment: '.($response['statusMessage'] ?? 'unknown error'));
        }

        return new PaymentInitiation($response['bkashURL'], $response['paymentID'], $response);
    }

    public function handleCallback(Request $request): PaymentResult
    {
        $paymentId = $request->string('paymentID')->toString();
        $status = $request->string('status')->toString();

        if ($paymentId === '') {
            throw new PaymentException('bKash callback is missing paymentID.');
        }

        if ($status !== 'success') {
            return new PaymentResult(
                status: $status === 'cancel' ? PaymentStatus::Cancelled : PaymentStatus::Failed,
                gatewayRef: $paymentId,
                orderNumber: null,
                raw: $request->query(),
                message: "bKash reported status [{$status}].",
            );
        }

        $execution = $this->api()->post('/tokenized/checkout/execute', ['paymentID' => $paymentId])->json() ?? [];

        // A repeated callback can't execute twice; ask for the final status instead.
        if (! $this->isCompleted($execution)) {
            $execution = $this->api()->post('/tokenized/checkout/payment/status', ['paymentID' => $paymentId])->json() ?? [];
        }

        if (! $this->isCompleted($execution)) {
            return new PaymentResult(
                status: PaymentStatus::Failed,
                gatewayRef: $paymentId,
                orderNumber: $execution['merchantInvoiceNumber'] ?? null,
                raw: $execution,
                message: $execution['statusMessage'] ?? 'bKash did not complete the payment.',
            );
        }

        return new PaymentResult(
            status: PaymentStatus::Success,
            gatewayRef: $paymentId,
            orderNumber: $execution['merchantInvoiceNumber'] ?? null,
            amount: Money::fromTaka((string) $execution['amount']),
            transactionId: $execution['trxID'] ?? null,
            raw: $execution,
        );
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function isCompleted(array $response): bool
    {
        return ($response['statusCode'] ?? null) === self::SUCCESS_CODE
            && ($response['transactionStatus'] ?? null) === 'Completed'
            && isset($response['amount']);
    }

    private function api(): PendingRequest
    {
        return $this->http()->withHeaders([
            'Authorization' => $this->token(),
            'X-APP-Key' => (string) config('services.bkash.app_key'),
        ]);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl((string) config('services.bkash.base_url'))
            ->timeout((int) config('payments.http_timeout'))
            ->acceptJson()
            ->asJson();
    }

    /**
     * id_token is valid for 1 hour; cache it slightly less.
     */
    private function token(): string
    {
        return Cache::remember('payments.bkash.id_token', now()->addMinutes(50), function () {
            $response = $this->http()
                ->withHeaders([
                    'username' => (string) config('services.bkash.username'),
                    'password' => (string) config('services.bkash.password'),
                ])
                ->post('/tokenized/checkout/token/grant', [
                    'app_key' => config('services.bkash.app_key'),
                    'app_secret' => config('services.bkash.app_secret'),
                ])->json();

            if (empty($response['id_token'])) {
                throw new PaymentException('bKash token grant failed: '.($response['statusMessage'] ?? $response['msg'] ?? 'unknown error'));
            }

            return (string) $response['id_token'];
        });
    }
}
