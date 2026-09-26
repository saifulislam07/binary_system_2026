<?php

namespace App\Listeners;

use App\Services\SystemHealth;
use Spatie\Backup\Events\BackupWasSuccessful;

class RecordSuccessfulBackup
{
    public function __construct(private SystemHealth $health) {}

    public function handle(BackupWasSuccessful $event): void
    {
        $this->health->recordBackup();
    }
}
