<?php

namespace App\Console\Commands;

use App\Services\BackupRestoreVerifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('backup:verify-restore {--disk= : Backup disk to read (default: the first of BACKUP_DISKS)}')]
#[Description('Restore the newest backup into a scratch database, check it, and drop it again')]
class VerifyBackupRestore extends Command
{
    public function handle(BackupRestoreVerifier $verifier): int
    {
        $result = $verifier->verify($this->option('disk') ? (string) $this->option('disk') : null);

        $this->info("Backup {$result['backup']} (made {$result['created_at']})");
        $this->table(['Check', 'Result', 'Detail'], array_map(
            fn (array $c) => [$c['check'], $c['ok'] ? 'OK' : 'FAILED', $c['detail']],
            $result['checks'],
        ));

        if (! $result['ok']) {
            Log::error('Backup restore check failed', $result);
            $this->error('Restore check FAILED.');

            return self::FAILURE;
        }

        Log::info('Backup restore check passed', ['backup' => $result['backup']]);
        $this->info('Restore check passed.');

        return self::SUCCESS;
    }
}
