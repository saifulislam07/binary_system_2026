<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Enums\PlacementSide;
use App\Models\BinaryNode;
use App\Models\Member;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-side queries over the placement tree for the member dashboard.
 */
class TeamService
{
    public const MAX_TREE_DEPTH = 4;

    public function __construct(private TeamVolumeService $volumes) {}

    /**
     * Downline size per leg: every member placed anywhere below the given
     * member, split by which of their two legs it hangs from.
     *
     * @return array{left: array{total: int, active: int}, right: array{total: int, active: int}}
     */
    public function legCounts(Member $member): array
    {
        $rows = DB::select(<<<'SQL'
            WITH RECURSIVE downline (id, leg, status) AS (
                SELECT id, placement_side, status FROM members WHERE placement_parent_id = ?
                UNION ALL
                SELECT m.id, downline.leg, m.status
                FROM members m
                JOIN downline ON m.placement_parent_id = downline.id
            )
            SELECT leg, COUNT(*) AS total, SUM(status = 'active') AS active
            FROM downline
            GROUP BY leg
            SQL, [$member->id]);

        $byLeg = collect($rows)->map(fn (object $row) => (array) $row)->keyBy('leg');
        $leg = fn (string $side) => [
            'total' => (int) ($byLeg[$side]['total'] ?? 0),
            'active' => (int) ($byLeg[$side]['active'] ?? 0),
        ];

        return ['left' => $leg('left'), 'right' => $leg('right')];
    }

    /**
     * Is `$node` the viewer or somewhere in the viewer's placement downline?
     */
    public function isInDownline(Member $viewer, Member $node): bool
    {
        return $viewer->is($node) || $this->volumes->ancestorsOf($node->id)->has($viewer->id);
    }

    /**
     * The tree below `$root`, `$depth` levels deep, loaded one level per
     * query. Nodes on the last loaded level have `children: null` and
     * `hasLeft`/`hasRight` flags so the UI can fetch them on demand.
     *
     * @return array<string, mixed>
     */
    public function subtree(Member $root, int $depth = 2): array
    {
        $depth = max(0, min($depth, self::MAX_TREE_DEPTH));

        /** @var array<int, array<string, mixed>> $nodes member_id => node */
        $nodes = [];
        $level = [$root->id];

        for ($d = 0; $d <= $depth && $level !== []; $d++) {
            $loaded = $this->loadLevel($level);
            $next = [];

            foreach ($level as $memberId) {
                $row = $loaded[$memberId] ?? null;

                if ($row === null) {
                    continue;
                }

                $nodes[$memberId] = $this->describe($row, expanded: $d < $depth);

                if ($d < $depth) {
                    $next = [...$next, ...array_filter([$row->binaryNode?->left_child_id, $row->binaryNode?->right_child_id])];
                }
            }

            $level = $next;
        }

        return $this->assemble($root->id, $nodes);
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, mixed>
     */
    private function assemble(int $memberId, array $nodes): array
    {
        $node = $nodes[$memberId];

        if ($node['children'] !== null) {
            $node['children'] = [
                'left' => $node['leftId'] !== null && isset($nodes[$node['leftId']]) ? $this->assemble($node['leftId'], $nodes) : null,
                'right' => $node['rightId'] !== null && isset($nodes[$node['rightId']]) ? $this->assemble($node['rightId'], $nodes) : null,
            ];
        }

        unset($node['leftId'], $node['rightId']);

        return $node;
    }

    /**
     * @param  list<int>  $memberIds
     * @return Collection<int, Member> keyed by id
     */
    private function loadLevel(array $memberIds): Collection
    {
        return Member::query()
            ->with(['user:id,name', 'package:id,name', 'binaryNode'])
            ->whereKey($memberIds)
            ->get()
            ->keyBy('id');
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(Member $member, bool $expanded): array
    {
        /** @var BinaryNode|null $node */
        $node = $member->binaryNode;
        $left = $node->left_lifetime_volume ?? 0;
        $right = $node->right_lifetime_volume ?? 0;

        return [
            'code' => $member->member_code,
            'name' => $member->user->name,
            'status' => $member->status->value,
            'active' => $member->status === MemberStatus::Active,
            'package' => $member->package?->name,
            'side' => $member->placement_side?->value,
            'leftBv' => intdiv($left, 100),
            'rightBv' => intdiv($right, 100),
            'teamBv' => intdiv($left + $right, 100),
            'hasLeft' => $node?->left_child_id !== null,
            'hasRight' => $node?->right_child_id !== null,
            'leftId' => $node?->childId(PlacementSide::Left),
            'rightId' => $node?->childId(PlacementSide::Right),
            'children' => $expanded ? [] : null,
        ];
    }
}
