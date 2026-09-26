<?php

namespace App\Notifications;

class PasswordChanged extends MemberNotification
{
    public function kind(): string
    {
        return 'security';
    }

    public function title(): string
    {
        return 'Your password was changed · পাসওয়ার্ড পরিবর্তিত';
    }

    public function message(object $notifiable): string
    {
        return 'Your account password was just changed. If this was not you, reset your password now and contact support.';
    }

    public function path(): string
    {
        return route('security.edit', absolute: false);
    }

    protected function urgent(): bool
    {
        return true;
    }
}
