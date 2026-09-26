<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp message to the member's +880 mobile number.
 *
 * TODO: wire up a WhatsApp Business API provider (Meta Cloud API or a
 * local BSP). Until then this stub only writes the message to the log.
 * Implement NotificationChannel in a gateway class and bind it:
 *   $this->app->bind(WhatsAppChannel::class, MetaCloudWhatsAppChannel::class);
 */
class WhatsAppChannel implements NotificationChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $to = method_exists($notifiable, 'routeNotificationFor') ? $notifiable->routeNotificationFor('whatsapp', $notification) : null;

        if (! is_string($to) || $to === '' || ! method_exists($notification, 'toWhatsApp')) {
            return;
        }

        Log::channel(config('notifications.stub_log_channel'))->info('[whatsapp stub] message not sent — no gateway configured', [
            'to' => $to,
            'notification' => $notification::class,
            'text' => $notification->toWhatsApp($notifiable),
        ]);
    }
}
