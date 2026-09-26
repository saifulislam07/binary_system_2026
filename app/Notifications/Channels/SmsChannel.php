<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * SMS to the member's +880 mobile number.
 *
 * TODO: wire up a Bangladeshi SMS provider (e.g. SSL Wireless, BulkSMSBD,
 * Alpha SMS). Until then this stub only writes the message to the log.
 * Implement NotificationChannel in a gateway class and bind it:
 *   $this->app->bind(SmsChannel::class, SslWirelessSmsChannel::class);
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
