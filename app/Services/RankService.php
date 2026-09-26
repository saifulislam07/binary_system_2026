<?php

namespace App\Services;

use App\DTOs\MemberStats;
use App\Enums\CommissionType;
use App\Enums\MemberStatus;
use App\Enums\PayoutStatus;
use App\Enums\WalletTransactionType;
use App\Models\Commission;
use App\Models\Member;
use App\Models\Rank;
use App\Models\RankAchievement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rule #11. A member holds the highest rank (by sort_order) whose personal
 * sales, team sales and active team thresholds they all meet.
 *
 * - Promotion only: ranks are never taken away.
 * - Skipping ranks records (and pays) each rank passed on the way, so every
 *   rank is achieved — and its bonus paid — exactly once.
 * - Idempotent: running it again changes nothing (unique member+rank).
 */
class RankService
{
    public function __construct(
        private MemberStatsService $stats,
        private WalletService $wallets,
    ) {}

    /**
     * @return list<RankAchievement> ranks newly achieved by this run
     */
    public function evaluate(Member $member, ?MemberStats $stats = null): array
    {
        if ($member->status !== MemberStatus::Active) {
            return [];
        }

        $stats ??= $this->stats->forMember($member);
        $qualified = $this->qualifyingRanks($stats);

        if ($qualified->isEmpty()) {
            return [];
        }

        return DB::transaction(function () use ($member, $qualified, $stats) {
            $member = Member::query()->lockForUpdate()->findOrFail($member->id);
            $already = $member->rankAchievements()->pluck('rank_id')->all();
            $new = [];

            foreach ($qualified as $rank) {
                if (in_array($rank->id, $already, true)) {
                    continue;
                }

                $achievement = RankAchievement::query()->create([
                    'member_id' => $member->id,
                    'rank_id' => $rank->id,
                    'achieved_at' => now(),
                ]);

                if ($rank->bonus_amount > 0) {
                    $commission = Commission::query()->create([
                        'member_id' => $member->id,
                        'type' => CommissionType::Rank,
                        'amount' => $rank->bonus_amount,
                        'cycle_date' => today(),
                        'status' => PayoutStatus::Paid,
                        'description' => "Rank bonus: {$rank->name}",
                    ]);

                    $this->wallets->credit($member, $rank->bonus_amount, WalletTransactionType::RankBonus, $commission, $commission->description);
                }

                activity('ranks')
                    ->performedOn($member)
                    ->withProperties([
                        'rank' => $rank->name,
                        'bonus' => $rank->bonus_amount,
                        'personal_sales' => $stats->personalSales,
                        'team_sales' => $stats->teamSales,
                        'active_team' => $stats->activeTeam,
                    ])
                    ->log("Promoted to {$rank->name}");

                $new[] = $achievement;
            }

            $highest = $qualified->last();

            if ($member->current_rank_id !== $highest->id
                && ($member->currentRank === null || $highest->sort_order > $member->currentRank->sort_order)) {
                $member->forceFill(['current_rank_id' => $highest->id])->save();
            }

            return $new;
        }, 3);
    }

    /**
     * Every rank whose thresholds are all met, lowest first.
     *
     * @return Collection<int, Rank>
     */
    public function qualifyingRanks(MemberStats $stats): Collection
    {
        return Rank::query()
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Rank $rank) => $stats->personalSales >= $rank->min_personal_sales
                && $stats->teamSales >= $rank->min_team_sales
                && $stats->activeTeam >= $rank->min_active_team)
            ->values();
    }

    /**
     * The next rank above the member's current one, with how far along
     * each requirement is (for the member dashboard).
     *
     * @return array{current: string, next: string|null, progress: list<array{label: string, have: int, need: int, money: bool}>}
     */
    public function progress(Member $member, ?MemberStats $stats = null): array
    {
        $stats ??= $this->stats->forMember($member);
        $current = $member->currentRank ?? Rank::query()->orderBy('sort_order')->first();
        $next = Rank::query()
            ->when($current, fn ($q) => $q->where('sort_order', '>', $current->sort_order))
            ->orderBy('sort_order')
            ->first();

        return [
            'current' => $current->name ?? 'Member',
            'next' => $next?->name,
            'progress' => $next === null ? [] : [
                ['label' => 'Personal sales', 'have' => $stats->personalSales, 'need' => $next->min_personal_sales, 'money' => true],
                ['label' => 'Team sales', 'have' => $stats->teamSales, 'need' => $next->min_team_sales, 'money' => true],
                ['label' => 'Active team', 'have' => $stats->activeTeam, 'need' => $next->min_active_team, 'money' => false],
            ],
        ];
    }
}
