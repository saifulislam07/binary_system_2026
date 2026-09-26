<?php

namespace App\Notifications;

use App\Models\Announcement;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use Illuminate\Support\Str;

/**
 * Admin broadcast. Always in-app; email/SMS/WhatsApp only when the admin
 * picked them AND the channel is switched on in config/notifications.php.
 */
class ImportantAnnouncement extends MemberNotification
{
    public function __construct(public Announcement $announcement)
    {
        parent::__construct();
    }

    public function kind(): string
    {
        return 'announcement';
    }

    public function title(): string
    {
        return $this->announcement->title;
    }

    public function message(object $notifiable): string
    {
        return $this->announcement->body;
    }

    public function via(object $notifiable): array
    {
        $picked = $this->announcement->channels;
        $channels = ['database'];

        if (in_array('mail', $picked, true) && config('notifications.channels.mail') && filled($notifiable->email ?? null)) {
            $channels[] = EmailChannel::class;
        }

        if (in_array('sms', $picked, true) && config('notifications.channels.sms')) {
            $channels[] = SmsChannel::class;
        }

        if (in_array('whatsapp', $picked, true) && config('notifications.channels.whatsapp')) {
            $channels[] = WhatsAppChannel::class;
        }

        return $channels;
    }

    public function toSms(object $notifiable): string
    {
        return config('app.name').': '.$this->announcement->title.' — '.Str::limit($this->announcement->body, 120);
    }

    protected function data(): array
    {
        return ['announcement_id' => $this->announcement->id];
    }
}
