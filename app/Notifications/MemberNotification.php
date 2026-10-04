<?php

namespace App\Notifications;

use App\Notifications\Channels\EmailChannel;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Base for everything sent to a member (App\Models\User). Always stored for
 * the in-app bell; also emailed, and — for urgent ones — sent by SMS and
 * WhatsApp when those channels are switched on in config/notifications.php.
 *
 * Queued, and only after the surrounding DB transaction commits, so a
 * rolled-back activation or payout never tells anyone anything. Texts use
 * __(): Laravel renders each notification in the member's preferred locale
 * (User::preferredLocale()), so it is stored and sent in their language.
 */
abstract class MemberNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }

    /**
     * Stable key the member app uses to pick an icon.
     */
    abstract public function kind(): string;

    /**
     * Short title, translated with __().
     */
    abstract public function title(): string;

    abstract public function message(object $notifiable): string;

    /**
     * Where the notification leads in the member app (relative path).
     */
    public function path(): ?string
    {
        return null;
    }

    public function actionText(): string
    {
        return __('Open');
    }

    /**
     * Worth a text message (SMS/WhatsApp), not just email + in-app.
     */
    protected function urgent(): bool
    {
        return false;
    }

    /**
     * Extra fields stored with the in-app notification.
     *
     * @return array<string, mixed>
     */
    protected function data(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (config('notifications.channels.mail') && filled($notifiable->email ?? null)) {
            $channels[] = EmailChannel::class;
        }

        if ($this->urgent()) {
            if (config('notifications.channels.sms')) {
                $channels[] = SmsChannel::class;
            }

            if (config('notifications.channels.whatsapp')) {
                $channels[] = WhatsAppChannel::class;
            }
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title())
            ->greeting(filled($notifiable->name ?? null) ? __('Hello :name,', ['name' => $notifiable->name]) : __('Hello,'))
            ->line($this->message($notifiable));

        if ($this->path() !== null) {
            $mail->action($this->actionText(), url($this->path()));
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind(),
            'title' => $this->title(),
            'message' => $this->message($notifiable),
            'url' => $this->path(),
            ...$this->data(),
        ];
    }

    public function toSms(object $notifiable): string
    {
        return config('app.name').': '.$this->message($notifiable);
    }

    public function toWhatsApp(object $notifiable): string
    {
        return '*'.$this->title()."*\n".$this->message($notifiable);
    }

    /**
     * Values for the approved WhatsApp template ({{1}} title, {{2}} message)
     * — the Cloud API only allows templates for messages we start.
     *
     * @return array{0: string, 1: string}
     */
    public function toWhatsAppTemplate(object $notifiable): array
    {
        return [$this->title(), Str::limit($this->message($notifiable), 700)];
    }
}
