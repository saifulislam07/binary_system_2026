<?php

namespace App\Services;

use App\Enums\PlacementSide;
use App\Enums\SaleStatus;
use App\Exceptions\PlacementException;
use App\Models\Admin;
use App\Models\Member;
use App\Models\Sale;
use App\Models\VolumeConsumption;
use App\Models\VolumeLot;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Admin tool for edge cases: move a member — with their whole downline — to
 * a vacant slot elsewhere in the tree.
 *
 * Volume moves with them: every sale made inside the moved subtree is taken
 * off the old upline (lots marked `transferred`) and accrued to the new
 * upline, exactly as if the member had been placed there from the start.
 * That is only honest while none of that volume has been matched or
 * flushed by a commission cycle — otherwise the old upline has already been
 * paid for it — so such moves are refused.
 */
class PlacementAdjustmentService
{
    public function __construct(
        private TeamVolumeService $volumes,
        private TeamService $team,
    ) {}

    public function move(Member $member, Member $newParent, PlacementSide $side, Admin $admin, string $reason): Member
    {
        if (trim($reason) === '') {
            throw new PlacementException('A reason is required for a placement adjustment.');
        }

        return DB::transaction(function () use ($member, $newParent, $side, $admin, $reason) {
            $member = Member::query()->with('placementParent')->findOrFail($member->id);
            $newParent = Member::query()->findOrFail($newParent->id);

            $this->guard($member, $newParent, $side);

            $oldUpline = $this->volumes->ancestorsOf($member->id);       // member_id => side
            $newUpline = collect([$newParent->id => $side])->union($this->volumes->ancestorsOf($newParent->id));

            // Lock every node we touch, top-down (ascending id), like all tree writers.
            $nodes = $this->volumes->lockNodes(array_values(array_unique([
                $member->id, ...$oldUpline->keys()->all(), ...$newUpline->keys()->all(),
            ])));

            $parentNode = $nodes[$newParent->id] ?? throw new PlacementException('The new parent is not placed in the tree.');

            if (! $parentNode->hasVacancy($side)) {
                throw new PlacementException("{$newParent->member_code}'s {$side->value} slot is already taken.");
            }

            $subtreeIds = $this->subtreeMemberIds($member->id);
            $sales = Sale::query()
                ->whereIn('member_id', $subtreeIds)
                ->where('status', SaleStatus::Completed)
                ->where('bv_value', '>', 0)
                ->get(['id', 'bv_value']);

            $lotsToMove = VolumeLot::query()
                ->whereIn('sale_id', $sales->pluck('id'))
                ->whereIn('member_id', $oldUpline->keys())
                ->lockForUpdate()
                ->get();

            $this->ensureNothingMatched($lotsToMove);

            $before = $this->describePosition($member, $oldUpline);

            // 1. Take the subtree's volume off the old upline.
            foreach ($lotsToMove as $lot) {
                $node = $nodes[$lot->member_id];
                $node->{$lot->side->volumeColumn()} -= $lot->remaining;
                $node->{$lot->side->value.'_lifetime_volume'} -= $lot->bv;

                VolumeConsumption::query()->create([
                    'volume_lot_id' => $lot->id,
                    'kind' => VolumeConsumption::TRANSFERRED,
                    'bv' => $lot->remaining,
                ]);
                $lot->forceFill(['remaining' => 0])->save();
            }

            // 2. Relink: both copies of the tree fact change together.
            if ($member->placement_parent_id !== null && $member->placement_side !== null) {
                $oldParentNode = $nodes[$member->placement_parent_id];
                $oldParentNode->{$member->placement_side->childColumn()} = null;
            }

            $parentNode->{$side->childColumn()} = $member->id;
            $member->forceFill(['placement_parent_id' => $newParent->id, 'placement_side' => $side])->save();

            // 3. Accrue the subtree's volume to the new upline.
            $now = now();
            $newLots = [];

            foreach ($sales as $sale) {
                foreach ($newUpline as $ancestorId => $ancestorSide) {
                    $node = $nodes[$ancestorId];
                    $node->{$ancestorSide->volumeColumn()} += $sale->bv_value;
                    $node->{$ancestorSide->value.'_lifetime_volume'} += $sale->bv_value;

                    $newLots[] = [
                        'member_id' => $ancestorId,
                        'side' => $ancestorSide->value,
                        'sale_id' => $sale->id,
                        'bv' => $sale->bv_value,
                        'remaining' => $sale->bv_value,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            foreach (array_chunk($newLots, 500) as $chunk) {
                VolumeLot::query()->insert($chunk);
            }

            foreach ($nodes as $node) {
                $node->save();
            }

            $member->refresh();

            activity('tree')
                ->performedOn($member)
                ->causedBy($admin)
                ->withProperties([
                    'reason' => $reason,
                    'before' => $before,
                    'after' => $this->describePosition($member, $this->volumes->ancestorsOf($member->id)),
                    'subtree_size' => count($subtreeIds),
                    'sales_moved' => $sales->count(),
                    'bv_moved' => (int) $sales->sum('bv_value'),
                    'lots_transferred' => $lotsToMove->count(),
                    'lots_created' => count($newLots),
                ])
                ->log('Manual placement adjustment');

            return $member;
        }, 3);
    }

    private function guard(Member $member, Member $newParent, PlacementSide $side): void
    {
        if ($member->placement_parent_id === null) {
            throw new PlacementException('The top of the tree (or an unplaced member) cannot be moved.');
        }

        if ($newParent->binaryNode()->doesntExist()) {
            throw new PlacementException('The new parent is not placed in the tree.');
        }

        if ($member->placement_parent_id === $newParent->id && $member->placement_side === $side) {
            throw new PlacementException('The member is already in that position.');
        }

        if ($this->team->isInDownline($member, $newParent)) {
            throw new PlacementException('A member cannot be moved under themselves or their own downline.');
        }
    }

    /**
     * @param  Collection<int, VolumeLot>  $lots
     */
    private function ensureNothingMatched(Collection $lots): void
    {
        $consumed = VolumeConsumption::query()->whereIn('volume_lot_id', $lots->pluck('id'))->exists()
            || $lots->contains(fn (VolumeLot $lot) => $lot->remaining !== $lot->bv);

        if ($consumed) {
            throw new PlacementException(
                'Part of this team\'s volume has already been matched or flushed in a commission cycle. '
                .'Moving it now would pay the same volume twice, so this placement can no longer be changed.',
            );
        }
    }

    /**
     * @return list<int> the member and everyone placed below them
     */
    private function subtreeMemberIds(int $memberId): array
    {
        $rows = DB::select(<<<'SQL'
            WITH RECURSIVE subtree (id) AS (
                SELECT ?
                UNION ALL
                SELECT m.id FROM members m JOIN subtree ON m.placement_parent_id = subtree.id
            )
            SELECT id FROM subtree
            SQL, [$memberId]);

        return array_values(array_map(fn (object $row) => (int) ((array) $row)['id'], $rows));
    }

    /**
     * @param  Collection<int, PlacementSide>  $upline
     * @return array{parent: string|null, side: string|null, upline: list<string>}
     */
    private function describePosition(Member $member, Collection $upline): array
    {
        $codes = Member::query()->whereKey($upline->keys())->pluck('member_code', 'id');

        return [
            'parent' => $member->placement_parent_id !== null && isset($codes[$member->placement_parent_id]) ? (string) $codes[$member->placement_parent_id] : null,
            'side' => $member->placement_side?->value,
            'upline' => array_values($upline->keys()->map(fn (int $id) => (string) ($codes[$id] ?? $id))->all()),
        ];
    }
}
