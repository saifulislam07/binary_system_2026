<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;

/**
 * Email via Laravel Mail (the notification's toMail()).
 */
class EmailChannel implements NotificationChannel
{
    public function __construct(private MailChannel $mail) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $this->mail->send($notifiable, $notification);
    }
}
