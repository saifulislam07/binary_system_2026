<?php

namespace Tests\Feature\Notifications;

use App\Exceptions\MessageDeliveryException;
use App\Models\Announcement;
use App\Models\User;
use App\Notifications\Channels\MetaWhatsAppChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\ImportantAnnouncement;
use App\Notifications\PasswordChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class WhatsAppGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://graph.facebook.com/v26.0/1234567890/messages';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.whatsapp.driver' => 'meta',
            'services.whatsapp.meta.phone_number_id' => '1234567890',
            'services.whatsapp.meta.access_token' => 'secret-token',
            'services.whatsapp.meta.template' => 'account_update',
            'notifications.channels.mail' => false,
            'notifications.channels.whatsapp' => true,
        ]);

        $this->user = User::factory()->create(['phone' => '+8801712345678', 'locale' => 'bn']);
    }

    private function sent(): Request
    {
        $request = Http::recorded()->first()[0] ?? null;
        $this->assertInstanceOf(Request::class, $request);

        return $request;
    }

    public function test_the_driver_setting_picks_the_channel()
    {
        $this->assertInstanceOf(MetaWhatsAppChannel::class, app(WhatsAppChannel::class));

        config(['services.whatsapp.driver' => 'log']);
        $this->assertNotInstanceOf(MetaWhatsAppChannel::class, app(WhatsAppChannel::class));
    }

    public function test_an_urgent_notification_goes_out_as_the_template_in_the_members_language()
    {
        Http::fake([self::ENDPOINT => Http::response(['messaging_product' => 'whatsapp', 'messages' => [['id' => 'wamid.HBgM']]])]);

        $this->user->notify(new PasswordChanged);

        Http::assertSentCount(1);
        $request = $this->sent();
        $this->assertSame(self::ENDPOINT, $request->url());
        $this->assertTrue($request->hasHeader('Authorization', 'Bearer secret-token'));
        $this->assertSame('8801712345678', $request['to']);
        $this->assertSame('template', $request['type']);
        $this->assertSame('account_update', $request['template']['name']);
        $this->assertSame('bn', $request['template']['language']['code']);

        $parameters = $request['template']['components'][0]['parameters'];
        $this->assertCount(2, $parameters);
        $this->assertSame(__('Your password was changed', [], 'bn'), $parameters[0]['text']);
    }

    public function test_english_members_get_the_english_template()
    {
        Http::fake([self::ENDPOINT => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        $this->user->forceFill(['locale' => 'en'])->save();

        $this->user->notify(new PasswordChanged);

        $request = $this->sent();
        $this->assertSame('en', $request['template']['language']['code']);
        $this->assertSame('Your password was changed', $request['template']['components'][0]['parameters'][0]['text']);
    }

    public function test_template_values_are_flattened_to_one_line()
    {
        Http::fake([self::ENDPOINT => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        $announcement = (new Announcement)->forceFill(['title' => "Office\tclosed", 'body' => "Closed on Friday.\n\nOpen     again Saturday."]);

        app(WhatsAppChannel::class)->send($this->user, new ImportantAnnouncement($announcement));

        $parameters = $this->sent()['template']['components'][0]['parameters'];
        $this->assertSame('Office closed', $parameters[0]['text']);
        $this->assertSame('Closed on Friday. Open again Saturday.', $parameters[1]['text']);
    }

    public function test_a_permanent_refusal_is_logged_without_the_token()
    {
        Http::fake([self::ENDPOINT => Http::response(['error' => ['message' => 'Template name does not exist in the translation', 'type' => 'OAuthException', 'code' => 132001, 'fbtrace_id' => 'Abc']], 404)]);
        Log::spy();

        app(WhatsAppChannel::class)->send($this->user, new PasswordChanged);

        Log::shouldHaveReceived('error')->once()->withArgs(fn (string $message, array $context) => str_contains($message, '132001')
            && ! str_contains(json_encode($context), 'secret-token'));
    }

    public function test_rate_limits_and_server_errors_are_retried()
    {
        Http::fake([self::ENDPOINT => Http::sequence()
            ->push(['error' => ['message' => 'Rate limit hit', 'code' => 130429]], 400)
            ->push('upstream error', 503)
            ->push(['error' => ['message' => 'Too many requests', 'code' => 80007]], 429)]);

        foreach (range(1, 3) as $attempt) {
            try {
                app(WhatsAppChannel::class)->send($this->user, new PasswordChanged);
                $this->fail("Attempt {$attempt} should have thrown");
            } catch (MessageDeliveryException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_an_unreachable_api_is_retried()
    {
        Http::fake([self::ENDPOINT => Http::failedConnection()]);

        $this->expectException(MessageDeliveryException::class);
        app(WhatsAppChannel::class)->send($this->user, new PasswordChanged);
    }

    public function test_missing_credentials_send_nothing_and_say_so()
    {
        config(['services.whatsapp.meta.access_token' => null]);
        Http::fake();
        Log::spy();

        app(WhatsAppChannel::class)->send($this->user, new PasswordChanged);

        Http::assertNothingSent();
        Log::shouldHaveReceived('error')->once();
    }
}
