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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * SSLCommerz v4. Session API → customer pays on GatewayPageURL → browser
 * POSTs back to success/fail/cancel URL (and the IPN hits the same
 * endpoint) → we validate val_id with the Validation API before trusting it.
 */
class SslcommerzGateway implements PaymentGateway
{
    public function name(): GatewayName
    {
        return GatewayName::Sslcommerz;
    }

    public function initiate(Order $order): PaymentInitiation
    {
        $order->loadMissing('member.user', 'package');
        $user = $order->member->user;
        $callback = route('payments.callback', GatewayName::Sslcommerz->value);

        $response = Http::timeout((int) config('payments.http_timeout'))
            ->asForm()
            ->post($this->baseUrl().'/gwprocess/v4/api.php', [
                'store_id' => config('services.sslcommerz.store_id'),
                'store_passwd' => config('services.sslcommerz.store_password'),
                'total_amount' => Money::toDecimalString($order->amount),
                'currency' => 'BDT',
                'tran_id' => $order->order_number,
                'success_url' => $callback,
                'fail_url' => $callback,
                'cancel_url' => $callback,
                'ipn_url' => $callback,
                'cus_name' => $user->name,
                'cus_email' => $user->email,
                'cus_phone' => $user->phone ?? '',
                'cus_add1' => $order->member->address ?? 'N/A',
                'cus_city' => 'Dhaka',
                'cus_country' => 'Bangladesh',
                'shipping_method' => 'NO',
                'product_name' => $order->package->name.' package',
                'product_category' => 'package',
                'product_profile' => 'non-physical-goods',
            ])->json() ?? [];

        if (($response['status'] ?? null) !== 'SUCCESS' || empty($response['GatewayPageURL'])) {
            throw new PaymentException('SSLCommerz refused the payment: '.($response['failedreason'] ?? 'unknown error'));
        }

        return new PaymentInitiation($response['GatewayPageURL'], $response['sessionkey'] ?? null, $response);
    }

    public function handleCallback(Request $request): PaymentResult
    {
        $orderNumber = $request->string('tran_id')->toString();
        $valId = $request->string('val_id')->toString();
        $status = strtoupper($request->string('status')->toString());

        if ($orderNumber === '') {
            throw new PaymentException('SSLCommerz callback is missing tran_id.');
        }

        if ($valId === '' || ! in_array($status, ['VALID', 'VALIDATED'], true)) {
            return new PaymentResult(
                status: $status === 'CANCELLED' ? PaymentStatus::Cancelled : PaymentStatus::Failed,
                gatewayRef: null,
                orderNumber: $orderNumber,
                raw: $request->except(['store_passwd']),
                message: $request->string('error')->toString() ?: "SSLCommerz reported status [{$status}].",
            );
        }

        $validation = Http::timeout((int) config('payments.http_timeout'))
            ->get($this->baseUrl().'/validator/api/validationserverAPI.php', [
                'val_id' => $valId,
                'store_id' => config('services.sslcommerz.store_id'),
                'store_passwd' => config('services.sslcommerz.store_password'),
                'format' => 'json',
            ])->json() ?? [];

        $valid = in_array($validation['status'] ?? null, ['VALID', 'VALIDATED'], true)
            && ($validation['tran_id'] ?? null) === $orderNumber
            && ($validation['currency_type'] ?? $validation['currency'] ?? 'BDT') === 'BDT'
            && isset($validation['amount']);

        if (! $valid) {
            return new PaymentResult(
                status: PaymentStatus::Failed,
                gatewayRef: null,
                orderNumber: $orderNumber,
                raw: $validation,
                message: 'SSLCommerz validation failed.',
            );
        }

        return new PaymentResult(
            status: PaymentStatus::Success,
            gatewayRef: null,
            orderNumber: $orderNumber,
            amount: Money::fromTaka((string) $validation['amount']),
            transactionId: $validation['bank_tran_id'] ?? $valId,
            raw: $validation,
        );
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.sslcommerz.base_url'), '/');
    }
}
