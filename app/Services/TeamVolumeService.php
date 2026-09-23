<?php

namespace App\Services;

use App\Enums\PlacementSide;
use App\Models\BinaryNode;
use App\Models\Sale;
use App\Models\VolumeLot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Team volume accrual up the PLACEMENT tree (never the sponsor chain).
 */
class TeamVolumeService
{
    /**
     * Add the sale's BV to every placement ancestor, on the side the walk
     * came up through, and record one volume lot per ancestor. Idempotent
     * per sale.
     */
    public function accrueVolume(Sale $sale): void
    {
        if ($sale->bv_value <= 0) {
            return;
        }

        DB::transaction(function () use ($sale) {
            $ancestors = $this->ancestorsOf($sale->member_id);

            if ($ancestors->isEmpty()) {
                return;
            }

            // Lock top-down (ascending node id) — same order as PlacementService.
            $this->lockNodes($ancestors->keys()->all());

            // Checked under the locks, so two concurrent calls can't both accrue.
            if (VolumeLot::query()->where('sale_id', $sale->id)->exists()) {
                return;
            }

            foreach (PlacementSide::cases() as $side) {
                $ids = $ancestors->filter(fn (PlacementSide $s) => $s === $side)->keys()->all();

                if ($ids === []) {
                    continue;
                }

                BinaryNode::query()->whereIn('member_id', $ids)->incrementEach([
                    $side->volumeColumn() => $sale->bv_value,
                    $side->value.'_lifetime_volume' => $sale->bv_value,
                ]);
            }

            $now = now();

            VolumeLot::query()->insert($ancestors->map(fn (PlacementSide $side, int $memberId) => [
                'member_id' => $memberId,
                'side' => $side->value,
                'sale_id' => $sale->id,
                'bv' => $sale->bv_value,
                'remaining' => $sale->bv_value,
                'created_at' => $now,
                'updated_at' => $now,
            ])->values()->all());
        }, 3);
    }

    /**
     * Every placement ancestor of a member and the side of that ancestor the
     * member's branch hangs from, nearest first.
     *
     * @return Collection<int, PlacementSide> member_id => side
     */
    public function ancestorsOf(int $memberId): Collection
    {
        $rows = DB::select(<<<'SQL'
            WITH RECURSIVE chain (id, parent_id, side, depth) AS (
                SELECT id, placement_parent_id, placement_side, 0
                FROM members WHERE id = ?
                UNION ALL
                SELECT m.id, m.placement_parent_id, m.placement_side, chain.depth + 1
                FROM members m
                JOIN chain ON m.id = chain.parent_id
            )
            SELECT parent_id AS ancestor_id, side
            FROM chain
            WHERE parent_id IS NOT NULL
            ORDER BY depth
            SQL, [$memberId]);

        return collect($rows)
            ->map(fn (object $row) => (array) $row)
            ->mapWithKeys(fn (array $row) => [
                (int) $row['ancestor_id'] => PlacementSide::from((string) $row['side']),
            ]);
    }

    /**
     * Lock the given members' nodes in ascending node id (ancestors first).
     *
     * @param  array<array-key, int>  $memberIds
     * @return Collection<int, BinaryNode> keyed by member_id
     */
    public function lockNodes(array $memberIds): Collection
    {
        return BinaryNode::query()
            ->whereIn('member_id', $memberIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('member_id');
    }
}
