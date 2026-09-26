<?php

namespace App\Console\Commands;

use App\Enums\MemberStatus;
use App\Models\Member;
use App\Services\BonusService;
use App\Services\MemberStatsService;
use App\Services\RankService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ranks:evaluate')]
#[Description('Promote members whose stats meet a higher rank and pay leadership/sales threshold bonuses')]
class EvaluateRanks extends Command
{
    public function handle(MemberStatsService $stats, RankService $ranks, BonusService $bonuses): int
    {
        $all = $stats->forAll(); // bulk: one pass over the tree
        $promotions = 0;
        $bonusesPaid = 0;

        Member::query()
            ->where('status', MemberStatus::Active)
            ->whereIn('id', array_keys($all))
            ->chunkById(500, function ($members) use ($all, $ranks, $bonuses, &$promotions, &$bonusesPaid) {
                foreach ($members as $member) {
                    $promotions += count($ranks->evaluate($member, $all[$member->id]));
                    $bonusesPaid += count($bonuses->evaluateThresholds($member, $all[$member->id]));
                }
            });

        $this->info('Evaluated '.count($all)." members: {$promotions} promotions, {$bonusesPaid} threshold bonuses paid.");

        return self::SUCCESS;
    }
}
