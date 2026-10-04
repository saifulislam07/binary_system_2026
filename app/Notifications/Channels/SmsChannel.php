<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * SMS to the member's +880 mobile number — the log-only driver
 * (`SMS_DRIVER=log`). With `SMS_DRIVER=bulksmsbd`, AppServiceProvider
 * resolves this class to BulkSmsBdChannel instead. Another provider is
 * another NotificationChannel plus a case in that binding.
 */
class SmsChannel implements NotificationChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $to = method_exists($notifiable, 'routeNotificationFor') ? $notifiable->routeNotificationFor('sms', $notification) : null;

        if (! is_string($to) || $to === '' || ! method_exists($notification, 'toSms')) {
            return;
        }

        Log::channel(config('notifications.stub_log_channel'))->info('[sms stub] message not sent — no gateway configured', [
            'to' => $to,
            'notification' => $notification::class,
            'text' => $notification->toSms($notifiable),
        ]);
    }
}
