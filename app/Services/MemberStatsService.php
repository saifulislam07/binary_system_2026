<?php

namespace App\Services;

use App\DTOs\MemberStats;
use App\Enums\SaleStatus;
use App\Models\BinaryNode;
use App\Models\Member;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

/**
 * Personal sales, team sales and active team size — the inputs to ranks
 * (rule #11) and threshold bonuses.
 */
class MemberStatsService
{
    public function __construct(private TeamService $team) {}

    public function forMember(Member $member): MemberStats
    {
        $node = BinaryNode::query()->where('member_id', $member->id)->first();
        $legs = $this->team->legCounts($member);

        return new MemberStats(
            personalSales: (int) Sale::query()->where('member_id', $member->id)->where('status', SaleStatus::Completed)->sum('amount'),
            teamSales: $node === null ? 0 : $node->left_lifetime_volume + $node->right_lifetime_volume,
            activeTeam: $legs['left']['active'] + $legs['right']['active'],
        );
    }

    /**
     * Stats for every placed member in three queries (for the scheduled
     * evaluation — per-member recursive queries would be O(n²)).
     *
     * @return array<int, MemberStats> keyed by member id
     */
    public function forAll(): array
    {
        $personal = Sale::query()
            ->where('status', SaleStatus::Completed)
            ->groupBy('member_id')
            ->selectRaw('member_id, SUM(amount) AS total')
            ->pluck('total', 'member_id');

        // Every (ancestor, descendant) pair in the placement tree, counted
        // where the descendant is active.
        $activeTeam = collect(DB::select(<<<'SQL'
            WITH RECURSIVE pairs (ancestor_id, member_id) AS (
                SELECT placement_parent_id, id FROM members WHERE placement_parent_id IS NOT NULL
                UNION ALL
                SELECT m.placement_parent_id, pairs.member_id
                FROM pairs
                JOIN members m ON m.id = pairs.ancestor_id
                WHERE m.placement_parent_id IS NOT NULL
            )
            SELECT pairs.ancestor_id AS member_id, COUNT(*) AS active
            FROM pairs
            JOIN members d ON d.id = pairs.member_id
            WHERE d.status = 'active'
            GROUP BY pairs.ancestor_id
            SQL))
            ->map(fn (object $row) => (array) $row)
            ->mapWithKeys(fn (array $row) => [(int) $row['member_id'] => (int) $row['active']]);

        $stats = [];

        foreach (BinaryNode::query()->get(['member_id', 'left_lifetime_volume', 'right_lifetime_volume']) as $node) {
            $stats[$node->member_id] = new MemberStats(
                personalSales: (int) ($personal[$node->member_id] ?? 0),
                teamSales: $node->left_lifetime_volume + $node->right_lifetime_volume,
                activeTeam: (int) ($activeTeam[$node->member_id] ?? 0),
            );
        }

        return $stats;
    }
}
