<?php

namespace App\Console\Commands;

use App\Services\MatchingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

#[Signature('commission:run {date? : Cycle date (Y-m-d); defaults to yesterday}')]
#[Description('Run binary matching and pay commissions for one cycle')]
class RunCommissionCycle extends Command
{
    public function handle(MatchingService $matching): int
    {
        $date = $this->argument('date')
            ? Carbon::parse((string) $this->argument('date'))
            : Carbon::yesterday();

        $cycle = $matching->runCycle($date);

        $this->info(sprintf(
            'Cycle %s %s: %d members, %s BV matched.',
            $cycle->cycle_date->toDateString(),
            $cycle->status->value,
            $cycle->teamVolumes()->count(),
            number_format(intdiv((int) $cycle->teamVolumes()->sum('matched_volume'), 100)),
        ));

        return self::SUCCESS;
    }
}
