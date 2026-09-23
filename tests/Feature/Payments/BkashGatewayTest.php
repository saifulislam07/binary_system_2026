<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Order;
use App\Payments\Gateways\BkashGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BkashGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://bkash.test/v1.2.0-beta';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.bkash.base_url' => self::BASE,
            'services.bkash.app_key' => 'app-key',
            'services.bkash.app_secret' => 'app-secret',
            'services.bkash.username' => 'user',
            'services.bkash.password' => 'pass',
        ]);
    }

    private function fakeApi(array $execute, ?array $status = null): void
    {
        Http::fake([
            self::BASE.'/tokenized/checkout/token/grant' => Http::response(['id_token' => 'TOKEN', 'statusCode' => '0000']),
            self::BASE.'/tokenized/checkout/create' => Http::response([
                'statusCode' => '0000', 'paymentID' => 'PAY123', 'bkashURL' => 'https://sandbox.bka.sh/pay/PAY123',
            ]),
            self::BASE.'/tokenized/checkout/execute' => Http::response($execute),
            self::BASE.'/tokenized/checkout/payment/status' => Http::response($status ?? ['statusCode' => '2056', 'statusMessage' => 'Invalid']),
        ]);
    }

    public function test_initiate_grants_a_token_and_creates_the_payment()
    {
        $this->fakeApi([]);
        $order = Order::factory()->create(['amount' => 125_050]);

        $initiation = app(BkashGateway::class)->initiate($order);

        $this->assertSame('https://sandbox.bka.sh/pay/PAY123', $initiation->redirectUrl);
        $this->assertSame('PAY123', $initiation->gatewayRef);

        Http::assertSent(fn (ClientRequest $r) => str_ends_with($r->url(), '/token/grant')
            && $r->header('username') === ['user'] && $r['app_secret'] === 'app-secret');
        Http::assertSent(fn (ClientRequest $r) => str_ends_with($r->url(), '/checkout/create')
            && $r->header('Authorization') === ['TOKEN']
            && $r['amount'] === '1250.50'
            && $r['currency'] === 'BDT'
            && $r['merchantInvoiceNumber'] === $order->order_number
            && $r['callbackURL'] === route('payments.callback', 'bkash'));
    }

    public function test_initiate_throws_when_bkash_refuses()
    {
        Http::fake([
            self::BASE.'/tokenized/checkout/token/grant' => Http::response(['id_token' => 'TOKEN']),
            self::BASE.'/tokenized/checkout/create' => Http::response(['statusCode' => '2001', 'statusMessage' => 'Invalid App Key']),
        ]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Invalid App Key');

        app(BkashGateway::class)->initiate(Order::factory()->create());
    }

    public function test_success_callback_is_confirmed_by_executing_the_payment()
    {
        $this->fakeApi([
            'statusCode' => '0000', 'transactionStatus' => 'Completed', 'paymentID' => 'PAY123',
            'trxID' => 'TRX9', 'amount' => '1000.00', 'merchantInvoiceNumber' => 'ORD-1',
        ]);

        $result = app(BkashGateway::class)->handleCallback(Request::create('/cb', 'GET', ['paymentID' => 'PAY123', 'status' => 'success']));

        $this->assertSame(PaymentStatus::Success, $result->status);
        $this->assertSame(100_000, $result->amount);
        $this->assertSame('TRX9', $result->transactionId);
        $this->assertSame('PAY123', $result->gatewayRef);
    }

    public function test_repeated_callback_falls_back_to_payment_status()
    {
        $this->fakeApi(
            ['statusCode' => '2117', 'statusMessage' => 'Payment execution already been called before'],
            ['statusCode' => '0000', 'transactionStatus' => 'Completed', 'amount' => '1000', 'trxID' => 'TRX9'],
        );

        $result = app(BkashGateway::class)->handleCallback(Request::create('/cb', 'GET', ['paymentID' => 'PAY123', 'status' => 'success']));

        $this->assertSame(PaymentStatus::Success, $result->status);
        $this->assertSame(100_000, $result->amount);
    }

    public function test_query_string_success_is_not_trusted_without_a_completed_execution()
    {
        $this->fakeApi(['statusCode' => '2062', 'statusMessage' => 'Payment not completed']);

        $result = app(BkashGateway::class)->handleCallback(Request::create('/cb', 'GET', ['paymentID' => 'PAY123', 'status' => 'success']));

        $this->assertSame(PaymentStatus::Failed, $result->status);
        $this->assertNull($result->amount);
    }

    public function test_cancel_and_failure_callbacks_make_no_api_calls()
    {
        Http::fake();

        $gateway = app(BkashGateway::class);

        $this->assertSame(PaymentStatus::Cancelled, $gateway->handleCallback(Request::create('/cb', 'GET', ['paymentID' => 'P', 'status' => 'cancel']))->status);
        $this->assertSame(PaymentStatus::Failed, $gateway->handleCallback(Request::create('/cb', 'GET', ['paymentID' => 'P', 'status' => 'failure']))->status);

        Http::assertNothingSent();
    }
}
