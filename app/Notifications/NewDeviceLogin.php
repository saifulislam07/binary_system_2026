<?php

namespace App\Notifications;

use App\Models\LoginHistory;

/**
 * "Was this you?" — sent when a member signs in from a device or IP
 * address we haven't seen for them before.
 */
class NewDeviceLogin extends MemberNotification
{
    public function __construct(public LoginHistory $login)
    {
        parent::__construct();
    }

    public function kind(): string
    {
        return 'security';
    }

    public function title(): string
    {
        return 'New sign-in to your account · নতুন লগইন';
    }

    public function message(object $notifiable): string
    {
        return 'Your account was just signed in to from a new device or network (IP '.($this->login->ip ?? 'unknown').'). '
            .'If this was you, no action is needed. If not, change your password now.';
    }

    public function path(): string
    {
        return route('security.edit', absolute: false);
    }

    public function actionText(): string
    {
        return 'Change password · পাসওয়ার্ড পরিবর্তন';
    }

    protected function urgent(): bool
    {
        return true;
    }

    protected function data(): array
    {
        return [
            'ip' => $this->login->ip,
            'device' => $this->login->user_agent,
            'new_device' => $this->login->new_device,
            'new_ip' => $this->login->new_ip,
            'at' => $this->login->created_at->toIso8601String(),
        ];
    }
}
