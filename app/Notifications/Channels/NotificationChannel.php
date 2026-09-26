<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;

/**
 * A delivery channel for member notifications. Laravel resolves channel
 * classes from the container, so a different gateway is a new binding in
 * AppServiceProvider — notifications and call sites never change.
 */
interface NotificationChannel
{
    public function send(object $notifiable, Notification $notification): void;
}
