<?php

namespace App\Services;

use App\DTOs\PlacementSlot;
use App\Enums\MemberStatus;
use App\Enums\PlacementSide;
use App\Exceptions\PlacementException;
use App\Models\BinaryNode;
use App\Models\Member;
use Illuminate\Support\Facades\DB;

/**
 * Binary tree placement (spillover) and member activation.
 *
 * Locking: every read of binary_nodes here is a locking read, taken
 * top-down (parent before child). Locking reads always see the latest
 * committed tree even under REPEATABLE READ, and top-down order means two
 * concurrent placements wait on each other instead of deadlocking. Anything
 * else that locks several nodes (e.g. volume accrual) must also lock
 * ancestors before descendants — ascending binary_nodes.id gives that order.
 */
class PlacementService
{
    public function __construct(private MemberCodeGenerator $codes) {}

    /**
     * Find the first vacant slot on `$side` of `$underMember`: the direct
     * `$side` child slot if it's empty, otherwise a breadth-first search of
     * the subtree hanging off that side, checking left before right at each
     * node. Call inside a transaction so the visited rows stay locked.
     */
    public function findVacantSlot(Member $underMember, PlacementSide $side): PlacementSlot
    {
        $root = $this->lockNodes([$underMember->id])[$underMember->id]
            ?? throw new PlacementException("Member [{$underMember->id}] is not placed in the tree.");

        if ($root->hasVacancy($side)) {
            return new PlacementSlot($underMember, $side);
        }

        $level = [$root->childId($side)];

        while ($level !== []) {
            $nodes = $this->lockNodes($level);
            $nextLevel = [];

            foreach ($level as $memberId) {
                $node = $nodes[$memberId] ?? throw new PlacementException("Member [{$memberId}] is in the tree but has no binary node.");

                foreach ([PlacementSide::Left, PlacementSide::Right] as $childSide) {
                    if ($node->hasVacancy($childSide)) {
                        return new PlacementSlot(Member::query()->findOrFail($memberId), $childSide);
                    }

                    $nextLevel[] = $node->childId($childSide);
                }
            }

            $level = array_values(array_filter($nextLevel));
        }

        // Unreachable: every finite tree has a vacant slot at its leaves.
        throw new PlacementException('No vacant slot found.');
    }

    /**
     * Place `$newMember` in the first vacant slot on `$preferredSide` below
     * `$sponsor`, and create the new member's own binary node.
     */
    public function place(Member $newMember, Member $sponsor, PlacementSide $preferredSide): PlacementSlot
    {
        return DB::transaction(function () use ($newMember, $sponsor, $preferredSide) {
            if ($newMember->placement_parent_id !== null || $newMember->binaryNode()->exists()) {
                throw new PlacementException("Member [{$newMember->id}] is already placed.");
            }

            $slot = $this->findVacantSlot($sponsor, $preferredSide);

            // The parent row is already locked by findVacantSlot(); the slot can't be taken under us.
            BinaryNode::query()
                ->where('member_id', $slot->parent->id)
                ->update([$slot->side->childColumn() => $newMember->id]);

            $newMember->forceFill([
                'placement_parent_id' => $slot->parent->id,
                'placement_side' => $slot->side,
            ])->save();

            BinaryNode::query()->create(['member_id' => $newMember->id]);

            return $slot;
        });
    }

    /**
     * pending → active: assign the member code, place the member in the tree
     * under their sponsor, and open their wallet — all or nothing.
     * Idempotent: activating an already-active member is a no-op, so a
     * repeated payment callback can call this safely.
     */
    public function activateMember(Member $member): Member
    {
        return DB::transaction(function () use ($member) {
            $member = Member::query()->lockForUpdate()->findOrFail($member->id);

            if ($member->status === MemberStatus::Active) {
                return $member;
            }

            if ($member->status !== MemberStatus::Pending) {
                throw new PlacementException("Only pending members can be activated; member [{$member->id}] is {$member->status->value}.");
            }

            $code = $this->codes->next();
            $slot = null;

            if ($member->sponsor_id !== null) {
                $sponsor = Member::query()->findOrFail($member->sponsor_id);
                $slot = $this->place($member, $sponsor, $member->preferred_side ?? PlacementSide::Left);
            } else {
                $this->placeAsRoot($member);
            }

            $member->forceFill([
                'member_code' => $code,
                'status' => MemberStatus::Active,
                'activated_at' => now(),
            ])->save();

            $member->wallet()->firstOrCreate();

            activity('tree')
                ->performedOn($member)
                ->withProperties([
                    'member_code' => $code,
                    'sponsor_id' => $member->sponsor_id,
                    'placement_parent_id' => $slot?->parent->id,
                    'placement_side' => $slot?->side->value,
                    'preferred_side' => $member->preferred_side?->value,
                ])
                ->log('Member activated and placed');

            return $member;
        });
    }

    /**
     * Only the very first member may sit at the top of the tree without a sponsor.
     */
    private function placeAsRoot(Member $member): void
    {
        if (BinaryNode::query()->lockForUpdate()->exists()) {
            throw new PlacementException("Member [{$member->id}] has no sponsor, and the tree already has a root.");
        }

        BinaryNode::query()->create(['member_id' => $member->id]);
    }

    /**
     * @param  list<int|null>  $memberIds
     * @return array<int, BinaryNode> keyed by member_id
     */
    private function lockNodes(array $memberIds): array
    {
        return BinaryNode::query()
            ->whereIn('member_id', array_filter($memberIds))
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('member_id')
            ->all();
    }
}
