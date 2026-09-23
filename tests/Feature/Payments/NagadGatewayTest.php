<?php

namespace Tests\Feature\Payments;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Payments\Gateways\NagadGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Uses two freshly generated RSA key pairs — one playing Nagad (PG), one the
 * merchant — so encryption, signing and decryption are exercised for real.
 */
class NagadGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://nagad.test/api/dfs';

    private string $pgPrivate;

    private string $merchantPublic;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->pgPrivate, $pgPublic] = $this->keyPair();
        [$merchantPrivate, $this->merchantPublic] = $this->keyPair();

        config([
            'services.nagad.base_url' => self::BASE,
            'services.nagad.merchant_id' => '683002007104225',
            'services.nagad.merchant_number' => '01711111111',
            // Stored as bare base64, the way Nagad hands keys out.
            'services.nagad.public_key' => $this->bare($pgPublic),
            'services.nagad.private_key' => $this->bare($merchantPrivate),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function keyPair(): array
    {
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];

        // Windows PHP builds need an explicit openssl.cnf to generate keys.
        $bundledConfig = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        if (getenv('OPENSSL_CONF') === false && is_file($bundledConfig)) {
            $options['config'] = $bundledConfig;
        }

        $key = openssl_pkey_new($options);
        $this->assertNotFalse($key, 'Could not generate an RSA key: '.openssl_error_string());
        openssl_pkey_export($key, $private, null, $options);

        return [$private, openssl_pkey_get_details($key)['key']];
    }

    private function bare(string $pem): string
    {
        return (string) preg_replace('/-----[^-]+-----|\s+/', '', $pem);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encryptForMerchant(array $payload): string
    {
        openssl_public_encrypt((string) json_encode($payload), $encrypted, $this->merchantPublic);

        return base64_encode($encrypted);
    }

    /**
     * @return array<string, mixed>
     */
    private function decryptAsPg(string $sensitiveData): array
    {
        openssl_private_decrypt((string) base64_decode($sensitiveData), $plain, $this->pgPrivate);

        return json_decode($plain, true);
    }

    public function test_initiate_sends_encrypted_signed_requests_and_returns_the_payment_page()
    {
        $order = Order::factory()->create(['amount' => 2_500_000]);
        $orderId = NagadGateway::nagadOrderId($order->order_number);

        Http::fake([
            self::BASE.'/check-out/initialize/*' => Http::response([
                'sensitiveData' => $this->encryptForMerchant(['paymentReferenceId' => 'REF777', 'challenge' => 'PGCHALLENGE']),
                'signature' => 'sig',
            ]),
            self::BASE.'/check-out/complete/REF777' => Http::response([
                'status' => 'Success', 'callBackUrl' => 'https://sandbox.mynagad.com/pay/REF777',
            ]),
        ]);

        $initiation = app(NagadGateway::class)->initiate($order);

        $this->assertSame('https://sandbox.mynagad.com/pay/REF777', $initiation->redirectUrl);
        $this->assertSame('REF777', $initiation->gatewayRef);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{1,20}$/', $orderId);

        Http::assertSent(function (ClientRequest $r) use ($orderId) {
            if (! str_contains($r->url(), '/check-out/initialize/683002007104225/'.$orderId)) {
                return false;
            }
            $payload = $this->decryptAsPg($r['sensitiveData']);
            $signed = openssl_verify((string) json_encode($payload), (string) base64_decode($r['signature']), $this->merchantPublic, OPENSSL_ALGO_SHA256);

            return $payload['orderId'] === $orderId && $signed === 1 && $r->header('X-KM-Api-Version') === ['v-0.2.0'];
        });

        Http::assertSent(function (ClientRequest $r) {
            if (! str_ends_with($r->url(), '/check-out/complete/REF777')) {
                return false;
            }
            $payload = $this->decryptAsPg($r['sensitiveData']);

            return $payload['amount'] === '25000.00'
                && $payload['currencyCode'] === '050'
                && $payload['challenge'] === 'PGCHALLENGE'
                && $r['merchantCallbackURL'] === route('payments.callback', 'nagad');
        });
    }

    public function test_success_callback_is_confirmed_with_the_verify_api()
    {
        Http::fake([
            self::BASE.'/verify/payment/REF777' => Http::response([
                'status' => 'Success', 'amount' => '25000', 'issuerPaymentRefNo' => 'NGD1', 'paymentRefId' => 'REF777',
            ]),
        ]);

        $result = app(NagadGateway::class)->handleCallback(
            Request::create('/cb', 'GET', ['payment_ref_id' => 'REF777', 'status' => 'Success']),
        );

        $this->assertSame(PaymentStatus::Success, $result->status);
        $this->assertSame(2_500_000, $result->amount);
        $this->assertSame('NGD1', $result->transactionId);
    }

    public function test_unverified_success_is_rejected()
    {
        Http::fake([self::BASE.'/verify/payment/REF777' => Http::response(['status' => 'Failed', 'message' => 'Not paid'])]);

        $result = app(NagadGateway::class)->handleCallback(
            Request::create('/cb', 'GET', ['payment_ref_id' => 'REF777', 'status' => 'Success']),
        );

        $this->assertSame(PaymentStatus::Failed, $result->status);
    }

    public function test_aborted_callback_is_a_cancellation()
    {
        Http::fake();

        $result = app(NagadGateway::class)->handleCallback(
            Request::create('/cb', 'GET', ['payment_ref_id' => 'REF777', 'status' => 'Aborted']),
        );

        $this->assertSame(PaymentStatus::Cancelled, $result->status);
        Http::assertNothingSent();
    }
}
