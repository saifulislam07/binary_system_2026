<?php

namespace App\Notifications;

use App\Notifications\Channels\EmailChannel;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The password-reset link (Fortify's forgot-password flow), queued and sent
 * through our EmailChannel. Email only: the token must never be stored in
 * the in-app notifications table or sent by SMS.
 */
class ResetPasswordLink extends ResetPassword implements ShouldQueue
{
    use Queueable;

    /**
     * @return list<string>
     */
    public function via($notifiable): array
    {
        return [EmailChannel::class];
    }

    protected function buildMailMessage($url): MailMessage
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Reset your password · পাসওয়ার্ড রিসেট')
            ->line('We received a request to reset the password for your account.')
            ->action('Reset password · রিসেট করুন', $url)
            ->line("This link expires in {$minutes} minutes.")
            ->line('If you did not ask for this, you can ignore this email — your password stays the same.');
    }
}
