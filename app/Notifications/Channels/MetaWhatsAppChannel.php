<?php

namespace App\Notifications\Channels;

use App\Exceptions\MessageDeliveryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp through Meta's WhatsApp Business Cloud API. Bound in place of
 * WhatsAppChannel when `WHATSAPP_DRIVER=meta`.
 *
 * Messages a business starts must use an approved template, so every
 * notification goes out as one utility template (WHATSAPP_TEMPLATE) with two
 * body values — {{1}} title, {{2}} message — in the member's language (the
 * notification is rendered in their locale; WHATSAPP_LANGUAGES maps it to a
 * template language). The template must exist in WhatsApp Manager in each
 * of those languages.
 *
 * Rate limits, Meta-side errors and network failures throw so the queue
 * retries; other refusals (bad number, template, token) are logged.
 */
class MetaWhatsAppChannel implements NotificationChannel
{
    /** Graph error codes worth retrying (temporary or throughput limits). */
    private const RETRYABLE = [1, 2, 4, 80007, 130429, 131000, 131056, 133004];

    public function send(object $notifiable, Notification $notification): void
    {
        $to = method_exists($notifiable, 'routeNotificationFor') ? $notifiable->routeNotificationFor('whatsapp', $notification) : null;

        if (! is_string($to) || $to === '' || ! method_exists($notification, 'toWhatsAppTemplate')) {
            return;
        }

        $config = (array) config('services.whatsapp.meta');

        if (blank($config['phone_number_id'] ?? null) || blank($config['access_token'] ?? null) || blank($config['template'] ?? null)) {
            Log::error('[whatsapp] Meta Cloud API is selected but WHATSAPP_PHONE_NUMBER_ID / WHATSAPP_ACCESS_TOKEN / WHATSAPP_TEMPLATE are not set — message not sent', [
                'notification' => $notification::class,
            ]);

            return;
        }

        $parameters = array_map(
            // Template values may not hold newlines, tabs or runs of spaces.
            fn (string $text) => ['type' => 'text', 'text' => trim((string) preg_replace('/\s+/u', ' ', $text))],
            array_values($notification->toWhatsAppTemplate($notifiable)),
        );

        try {
            $response = Http::baseUrl(rtrim((string) $config['graph_url'], '/').'/'.$config['version'])
                ->withToken((string) $config['access_token'])
                ->timeout((int) ($config['timeout'] ?? 10))
                ->acceptJson()
                ->post("{$config['phone_number_id']}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => ltrim($to, '+'),
                    'type' => 'template',
                    'template' => [
                        'name' => $config['template'],
                        'language' => ['code' => $this->languageCode($config)],
                        'components' => [['type' => 'body', 'parameters' => $parameters]],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new MessageDeliveryException('WhatsApp Cloud API could not be reached: '.$e->getMessage(), previous: $e);
        }

        if ($response->successful() && $response->json('messages.0.id') !== null) {
            return;
        }

        $code = (int) $response->json('error.code');
        $reason = (string) ($response->json('error.message') ?? 'unknown error');

        if ($response->serverError() || $response->status() === 429 || in_array($code, self::RETRYABLE, true)) {
            throw new MessageDeliveryException("WhatsApp Cloud API error {$code} (HTTP {$response->status()}): {$reason}");
        }

        Log::error("[whatsapp] Cloud API refused the message ({$code}: {$reason})", [
            'notification' => $notification::class,
            'http_status' => $response->status(),
            'error_subcode' => $response->json('error.error_subcode'),
            'fbtrace_id' => $response->json('error.fbtrace_id'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function languageCode(array $config): string
    {
        $languages = (array) ($config['languages'] ?? []);

        return (string) ($languages[app()->getLocale()] ?? $languages['en'] ?? 'en');
    }
}
