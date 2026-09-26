<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendAnnouncement implements ShouldQueue
{
    use Queueable;

    public function __construct(public Announcement $announcement) {}

    public function handle(AnnouncementService $announcements): void
    {
        $announcements->deliver($this->announcement);
    }
}
