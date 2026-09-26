<?php

namespace Tests\Feature\Members;

use App\Enums\MemberStatus;
use App\Enums\PlacementSide;
use App\Exceptions\PlacementException;
use App\Models\BinaryNode;
use App\Models\Member;
use App\Services\PlacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

#[Group('rule-1')]
#[Group('rule-2')]
#[Group('rule-3')]
class PlacementServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlacementService $placement;

    private Member $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->placement = app(PlacementService::class);
        $this->root = $this->placement->activateMember(Member::factory()->create());
    }

    private function join(Member $sponsor, PlacementSide $side): Member
    {
        return $this->placement->activateMember(
            Member::factory()->create(['sponsor_id' => $sponsor->id, 'preferred_side' => $side]),
        );
    }

    private function assertPlaced(Member $member, Member $parent, PlacementSide $side): void
    {
        $member->refresh();

        $this->assertSame($parent->id, $member->placement_parent_id, "{$member->member_code} has the wrong parent");
        $this->assertSame($side, $member->placement_side);
        $this->assertSame($member->id, $parent->binaryNode()->firstOrFail()->childId($side), 'Parent node does not point back at the child');
    }

    public function test_first_member_without_a_sponsor_becomes_the_root()
    {
        $this->assertSame(MemberStatus::Active, $this->root->status);
        $this->assertNull($this->root->placement_parent_id);
        $this->assertNotNull($this->root->binaryNode()->first());
    }

    public function test_placement_under_a_sponsor_with_two_vacant_slots_picks_the_direct_child_slot()
    {
        $left = $this->join($this->root, PlacementSide::Left);
        $right = $this->join($this->root, PlacementSide::Right);

        $this->assertPlaced($left, $this->root, PlacementSide::Left);
        $this->assertPlaced($right, $this->root, PlacementSide::Right);
    }

    public function test_placement_spills_breadth_first_down_the_chosen_side_when_direct_slots_are_full()
    {
        $a = $this->join($this->root, PlacementSide::Left);   // root.L
        $b = $this->join($this->root, PlacementSide::Right);  // root.R

        $c = $this->join($this->root, PlacementSide::Left);   // spills: a.L
        $d = $this->join($this->root, PlacementSide::Left);   // a.R
        $e = $this->join($this->root, PlacementSide::Left);   // level 3, left-most first: c.L
        $f = $this->join($this->root, PlacementSide::Left);   // c.R
        $g = $this->join($this->root, PlacementSide::Left);   // d.L
        $h = $this->join($this->root, PlacementSide::Right);  // right leg: b.L

        $this->assertPlaced($c, $a, PlacementSide::Left);
        $this->assertPlaced($d, $a, PlacementSide::Right);
        $this->assertPlaced($e, $c, PlacementSide::Left);
        $this->assertPlaced($f, $c, PlacementSide::Right);
        $this->assertPlaced($g, $d, PlacementSide::Left);
        $this->assertPlaced($h, $b, PlacementSide::Left);

        // Spillover members keep the root as their sponsor even though they sit deeper.
        $this->assertSame($this->root->id, $g->sponsor_id);
    }

    public function test_spillover_never_crosses_into_the_other_leg()
    {
        $this->join($this->root, PlacementSide::Left);
        $right = $this->join($this->root, PlacementSide::Right);

        // root.R's subtree is nearly empty, but a left-preferring member must stay in the left leg.
        $member = $this->join($this->root, PlacementSide::Left);

        $this->assertNotSame($right->id, $member->placement_parent_id);
    }

    public function test_find_vacant_slot_does_not_modify_the_tree()
    {
        $a = $this->join($this->root, PlacementSide::Left);

        $slot = $this->placement->findVacantSlot($this->root, PlacementSide::Left);

        $this->assertTrue($slot->parent->is($a));
        $this->assertSame(PlacementSide::Left, $slot->side);
        $this->assertNull($a->binaryNode()->firstOrFail()->left_child_id);
    }

    public function test_activation_assigns_code_status_placement_and_wallet()
    {
        $pending = Member::factory()->create(['sponsor_id' => $this->root->id, 'preferred_side' => PlacementSide::Right]);

        $this->assertNull($pending->member_code);

        $member = $this->placement->activateMember($pending);

        $this->assertMatchesRegularExpression('/^MBR-\d{6}$/', (string) $member->member_code);
        $this->assertSame(MemberStatus::Active, $member->status);
        $this->assertNotNull($member->activated_at);
        $this->assertNotNull($member->wallet()->first());
        $this->assertSame(0, $member->wallet()->firstOrFail()->balance);
        $this->assertPlaced($member, $this->root, PlacementSide::Right);

        $activity = Activity::query()->where('description', 'Member activated and placed')->latest('id')->firstOrFail();
        $this->assertSame($member->id, $activity->subject_id);
        $this->assertSame($this->root->id, $activity->properties['placement_parent_id']);
    }

    public function test_member_codes_are_sequential_and_unique_across_many_activations()
    {
        $codes = collect(range(1, 10))
            ->map(fn () => $this->join($this->root, PlacementSide::Left)->member_code)
            ->map(fn (?string $code) => (int) substr((string) $code, 4));

        $first = (int) substr((string) $this->root->member_code, 4) + 1;

        $this->assertSame(range($first, $first + 9), $codes->all());
    }

    public function test_activation_is_idempotent_and_does_not_consume_a_code_twice()
    {
        $member = $this->join($this->root, PlacementSide::Left);
        $next = $this->join($this->root, PlacementSide::Left);

        $again = $this->placement->activateMember($member);

        $this->assertSame($member->member_code, $again->member_code);
        $this->assertSame(1, BinaryNode::query()->where('member_id', $member->id)->count());
        $this->assertSame(
            (int) substr((string) $next->member_code, 4) + 1,
            (int) substr((string) $this->join($this->root, PlacementSide::Left)->member_code, 4),
        );
    }

    public function test_suspended_members_cannot_be_activated()
    {
        $member = Member::factory()->suspended()->create(['sponsor_id' => $this->root->id]);

        $this->expectException(PlacementException::class);

        $this->placement->activateMember($member);
    }

    public function test_a_second_member_without_a_sponsor_is_rejected()
    {
        $this->expectException(PlacementException::class);

        $this->placement->activateMember(Member::factory()->create());
    }

    public function test_failed_activation_rolls_back_and_releases_the_code()
    {
        $unplacedSponsor = Member::factory()->active()->create(); // active but has no binary node
        $member = Member::factory()->create(['sponsor_id' => $unplacedSponsor->id]);

        try {
            $this->placement->activateMember($member);
            $this->fail('Expected a PlacementException.');
        } catch (PlacementException) {
            // expected
        }

        $member->refresh();
        $this->assertSame(MemberStatus::Pending, $member->status);
        $this->assertNull($member->member_code);
        $this->assertNull($member->placement_parent_id);

        $rootNumber = (int) substr((string) $this->root->member_code, 4);
        $this->assertSame('MBR-'.($rootNumber + 1), $this->join($this->root, PlacementSide::Left)->member_code);
    }
}
