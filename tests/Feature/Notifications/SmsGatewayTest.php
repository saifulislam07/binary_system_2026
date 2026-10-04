<?php

namespace Tests\Feature\Notifications;

use App\Exceptions\MessageDeliveryException;
use App\Models\User;
use App\Notifications\Channels\BulkSmsBdChannel;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\PasswordChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SmsGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://bulksmsbd.net/api/smsapi';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.sms.driver' => 'bulksmsbd',
            'services.bulksmsbd.api_key' => 'test-key',
            'services.bulksmsbd.sender_id' => '8809617000000',
            'notifications.channels.mail' => false,
            'notifications.channels.sms' => true,
        ]);

        $this->user = User::factory()->create(['phone' => '+8801712345678', 'locale' => 'en']);
    }

    private function send(): void
    {
        app(SmsChannel::class)->send($this->user, new PasswordChanged);
    }

    public function test_the_driver_setting_picks_the_channel()
    {
        $this->assertInstanceOf(BulkSmsBdChannel::class, app(SmsChannel::class));

        config(['services.sms.driver' => 'log']);
        $this->assertInstanceOf(SmsChannel::class, app(SmsChannel::class));
        $this->assertNotInstanceOf(BulkSmsBdChannel::class, app(SmsChannel::class));
    }

    public function test_an_urgent_notification_is_posted_to_bulksmsbd()
    {
        Http::fake([self::ENDPOINT => Http::response(['response_code' => 202, 'message_id' => 59432678, 'success_message' => 'SMS Submitted Successfully', 'error_message' => ''])]);

        $this->user->notify(new PasswordChanged);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request->url() === self::ENDPOINT
            && $request->method() === 'POST'
            && $request['api_key'] === 'test-key'
            && $request['senderid'] === '8809617000000'
            && $request['type'] === 'text'
            && $request['number'] === '8801712345678'
            && str_contains((string) $request['message'], 'Your account password was just changed'));
    }

    public function test_a_permanent_refusal_is_logged_not_retried()
    {
        Http::fake([self::ENDPOINT => Http::response(['response_code' => 1007, 'error_message' => 'Balance Insufficient'])]);
        Log::spy();

        $this->send();

        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context) => str_contains($message, '1007: balance insufficient')
            && ! str_contains(json_encode($context), '8801712345678'));
    }

    public function test_a_provider_error_is_retried()
    {
        Http::fake([self::ENDPOINT => Http::response(['response_code' => 1005, 'error_message' => 'Internal Error'])]);

        $this->expectException(MessageDeliveryException::class);
        $this->send();
    }

    public function test_a_server_error_is_retried()
    {
        Http::fake([self::ENDPOINT => Http::response('Bad gateway', 502)]);

        $this->expectException(MessageDeliveryException::class);
        $this->send();
    }

    public function test_an_unreachable_gateway_is_retried()
    {
        Http::fake([self::ENDPOINT => Http::failedConnection()]);

        $this->expectException(MessageDeliveryException::class);
        $this->send();
    }

    public function test_missing_credentials_send_nothing_and_say_so()
    {
        config(['services.bulksmsbd.api_key' => null]);
        Http::fake();
        Log::spy();

        $this->send();

        Http::assertNothingSent();
        Log::shouldHaveReceived('error')->once();
    }

    public function test_members_without_a_phone_are_skipped()
    {
        Http::fake();
        $this->user->forceFill(['phone' => null])->save();

        $this->send();

        Http::assertNothingSent();
    }
}
