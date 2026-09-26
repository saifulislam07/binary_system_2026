<?php

namespace App\Jobs;

use App\Services\SystemHealth;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Dispatched by the scheduler every five minutes; if no worker picks it
 * up, the queue heartbeat goes stale and /up starts failing.
 */
class QueueHeartbeat implements ShouldQueue
{
    use Queueable;

    public function handle(SystemHealth $health): void
    {
        $health->recordQueueHeartbeat();
    }
}
