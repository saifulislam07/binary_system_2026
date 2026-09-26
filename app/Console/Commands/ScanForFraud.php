<?php

namespace App\Console\Commands;

use App\Services\FraudScanService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fraud:scan')]
#[Description('Flag suspicious withdrawals for admin review (never blocks anything)')]
class ScanForFraud extends Command
{
    public function handle(FraudScanService $scanner): int
    {
        $raised = $scanner->scanRecent();

        $this->info("{$raised} new flag(s) raised.");

        return self::SUCCESS;
    }
}
