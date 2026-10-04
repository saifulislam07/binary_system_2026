<?php

namespace App\Notifications\Channels;

use App\Exceptions\MessageDeliveryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS through BulkSMSBD (bulksmsbd.net). Bound in place of SmsChannel when
 * `SMS_DRIVER=bulksmsbd`.
 *
 * API: POST {base_url}/smsapi with api_key, senderid, number (880XXXXXXXXXX),
 * message and type. The reply is JSON whose response_code is 202 when the
 * message was accepted. Provider errors (1005) and network failures throw,
 * so the queue retries; anything else (bad number, sender ID, balance,
 * IP whitelist, account) is logged as an error — retrying wouldn't help.
 */
class BulkSmsBdChannel implements NotificationChannel
{
    /** Response codes worth retrying. */
    private const RETRYABLE = [1005];

    private const ERRORS = [
        1001 => 'invalid number',
        1002 => 'sender ID not correct or disabled',
        1003 => 'required fields missing',
        1005 => 'internal error at BulkSMSBD',
        1006 => 'balance validity not available',
        1007 => 'balance insufficient',
        1011 => 'user ID not found',
        1012 => 'masking SMS must be sent in Bengali',
        1013 => 'sender ID has no gateway for this API key',
        1018 => 'account disabled',
        1031 => 'account not verified',
        1032 => 'server IP not whitelisted in BulkSMSBD',
    ];

    public function send(object $notifiable, Notification $notification): void
    {
        $to = method_exists($notifiable, 'routeNotificationFor') ? $notifiable->routeNotificationFor('sms', $notification) : null;

        if (! is_string($to) || $to === '' || ! method_exists($notification, 'toSms')) {
            return;
        }

        $config = (array) config('services.bulksmsbd');

        if (blank($config['api_key'] ?? null) || blank($config['sender_id'] ?? null)) {
            Log::error('[sms] BulkSMSBD is selected but BULKSMSBD_API_KEY / BULKSMSBD_SENDER_ID are not set — message not sent', [
                'notification' => $notification::class,
            ]);

            return;
        }

        try {
            $response = Http::baseUrl((string) $config['base_url'])
                ->timeout((int) ($config['timeout'] ?? 10))
                ->asForm()
                ->acceptJson()
                ->post('smsapi', [
                    'api_key' => $config['api_key'],
                    'senderid' => $config['sender_id'],
                    'type' => $config['type'] ?? 'text',
                    'number' => ltrim($to, '+'),
                    'message' => (string) $notification->toSms($notifiable),
                ]);
        } catch (ConnectionException $e) {
            throw new MessageDeliveryException('BulkSMSBD could not be reached: '.$e->getMessage(), previous: $e);
        }

        if ($response->serverError()) {
            throw new MessageDeliveryException("BulkSMSBD returned HTTP {$response->status()}.");
        }

        $code = (int) $response->json('response_code');

        if ($code === 202) {
            return;
        }

        $reason = self::ERRORS[$code] ?? (string) ($response->json('error_message') ?: 'unknown error');

        if (in_array($code, self::RETRYABLE, true)) {
            throw new MessageDeliveryException("BulkSMSBD error {$code}: {$reason}.");
        }

        Log::error("[sms] BulkSMSBD refused the message ({$code}: {$reason})", [
            'to' => $this->masked($to),
            'notification' => $notification::class,
            'http_status' => $response->status(),
        ]);
    }

    /**
     * Keep full member numbers out of the logs.
     */
    private function masked(string $phone): string
    {
        return substr($phone, 0, 6).str_repeat('•', max(0, strlen($phone) - 9)).substr($phone, -3);
    }
}
