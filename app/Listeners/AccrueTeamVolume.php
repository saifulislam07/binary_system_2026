<?php

namespace App\Listeners;

use App\Events\SaleCompleted;
use App\Services\TeamVolumeService;

/**
 * Runs synchronously inside the payment transaction (see SaleCompleted).
 */
class AccrueTeamVolume
{
    public function __construct(private TeamVolumeService $volumes) {}

    public function handle(SaleCompleted $event): void
    {
        $this->volumes->accrueVolume($event->sale);
    }
}
