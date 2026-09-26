<?php

namespace App\Notifications;

use App\Models\LoginHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Was this you?" — sent when a member signs in from a device or IP
 * address we haven't seen for them before.
 */
class NewDeviceLogin extends Notification
{
    use Queueable;

    public function __construct(public LoginHistory $login) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New sign-in to your account · নতুন লগইন')
            ->line('Your account was just signed in to from a new device or network.')
            ->line('IP address: '.($this->login->ip ?? 'unknown'))
            ->line('Device: '.($this->login->user_agent ?: 'unknown'))
            ->line('If this was you, no action is needed. If not, change your password now.')
            ->action('Change password', route('security.edit'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'new_device_login',
            'title' => 'New sign-in to your account',
            'ip' => $this->login->ip,
            'new_device' => $this->login->new_device,
            'new_ip' => $this->login->new_ip,
            'at' => $this->login->created_at->toIso8601String(),
        ];
    }
}
