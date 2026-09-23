<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Payments\Gateways\SslcommerzGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SslcommerzGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://ssl.test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.sslcommerz.base_url' => self::BASE,
            'services.sslcommerz.store_id' => 'store',
            'services.sslcommerz.store_password' => 'secret',
        ]);
    }

    public function test_initiate_opens_a_session_and_returns_the_gateway_page()
    {
        Http::fake([
            self::BASE.'/gwprocess/v4/api.php' => Http::response([
                'status' => 'SUCCESS', 'GatewayPageURL' => 'https://sandbox.sslcommerz.com/pay/abc', 'sessionkey' => 'SESS1',
            ]),
        ]);
        $order = Order::factory()->create(['amount' => 500_000]);

        $initiation = app(SslcommerzGateway::class)->initiate($order);

        $this->assertSame('https://sandbox.sslcommerz.com/pay/abc', $initiation->redirectUrl);
        $this->assertSame('SESS1', $initiation->gatewayRef);
        Http::assertSent(fn (ClientRequest $r) => $r['tran_id'] === $order->order_number
            && $r['total_amount'] === '5000.00'
            && $r['store_passwd'] === 'secret'
            && $r['ipn_url'] === route('payments.callback', 'sslcommerz'));
    }

    public function test_valid_callback_is_confirmed_with_the_validation_api()
    {
        Http::fake([
            self::BASE.'/validator/*' => Http::response([
                'status' => 'VALID', 'tran_id' => 'ORD-1', 'amount' => '5000.00', 'currency_type' => 'BDT', 'bank_tran_id' => 'BANK1',
            ]),
        ]);

        $result = app(SslcommerzGateway::class)->handleCallback(
            Request::create('/cb', 'POST', ['tran_id' => 'ORD-1', 'val_id' => 'VAL1', 'status' => 'VALID', 'amount' => '999999']),
        );

        $this->assertSame(PaymentStatus::Success, $result->status);
        $this->assertSame(500_000, $result->amount, 'Amount must come from the validation API, not the POST body');
        $this->assertSame('ORD-1', $result->orderNumber);
        $this->assertSame('BANK1', $result->transactionId);
        Http::assertSent(fn (ClientRequest $r) => $r['val_id'] === 'VAL1' && $r['store_id'] === 'store');
    }

    public function test_validation_for_a_different_transaction_is_rejected()
    {
        Http::fake([
            self::BASE.'/validator/*' => Http::response(['status' => 'VALID', 'tran_id' => 'ORD-OTHER', 'amount' => '5000.00']),
        ]);

        $result = app(SslcommerzGateway::class)->handleCallback(
            Request::create('/cb', 'POST', ['tran_id' => 'ORD-1', 'val_id' => 'VAL1', 'status' => 'VALID']),
        );

        $this->assertSame(PaymentStatus::Failed, $result->status);
    }

    public function test_failed_and_cancelled_callbacks_are_not_validated()
    {
        Http::fake();
        $gateway = app(SslcommerzGateway::class);

        $this->assertSame(PaymentStatus::Failed, $gateway->handleCallback(Request::create('/cb', 'POST', ['tran_id' => 'ORD-1', 'status' => 'FAILED']))->status);
        $this->assertSame(PaymentStatus::Cancelled, $gateway->handleCallback(Request::create('/cb', 'POST', ['tran_id' => 'ORD-1', 'status' => 'CANCELLED']))->status);

        Http::assertNothingSent();
    }
}
