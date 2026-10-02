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
        return __('New sign-in to your account');
    }

    public function message(object $notifiable): string
    {
        return __('Your account was just signed in to from a new device or network (IP :ip). If this was you, no action is needed. If not, change your password now.', ['ip' => $this->login->ip ?? __('unknown')]);
    }

    public function path(): string
    {
        return route('security.edit', absolute: false);
    }

    public function actionText(): string
    {
        return __('Change password');
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
