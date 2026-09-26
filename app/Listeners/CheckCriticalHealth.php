<?php

namespace App\Listeners;

use App\Services\SystemHealth;
use Illuminate\Foundation\Events\DiagnosingHealth;
use RuntimeException;

/**
 * Makes GET /up (Laravel's health route) return 500 when the database or
 * cache is down — or, once HEALTH_REQUIRE_WORKERS is on, when cron or the
 * queue worker has stopped — so an uptime monitor pages someone.
 */
class CheckCriticalHealth
{
    public function __construct(private SystemHealth $health) {}

    public function handle(DiagnosingHealth $event): void
    {
        $failures = $this->health->criticalFailures();

        if ($failures !== []) {
            throw new RuntimeException('Health check failed: '.implode('; ', $failures));
        }
    }
}
