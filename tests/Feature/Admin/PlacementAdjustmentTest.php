<?php

namespace Tests\Feature\Admin;

use App\Enums\PlacementSide;
use App\Models\Admin;
use App\Models\Member;
use App\Models\VolumeLot;
use App\Services\MatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\BuildsNetwork;
use Tests\TestCase;

/**
 *          root
 *        /      \
 *       A        B
 *      /
 *     C  ── sold ৳1,000 ── (C has a child D who sold ৳500)
 *    /
 *   D
 */
class PlacementAdjustmentTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Admin $admin;

    private Member $root;

    private Member $a;

    private Member $b;

    private Member $c;

    private Member $d;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCommissionRules();
        $this->admin = Admin::factory()->superAdmin()->create();
        $this->root = $this->root();
        $this->a = $this->join($this->root, PlacementSide::Left);
        $this->b = $this->join($this->root, PlacementSide::Right);
        $this->c = $this->join($this->a, PlacementSide::Left);
        $this->d = $this->join($this->c, PlacementSide::Left);

        $this->sell($this->c, 1_000);
        $this->sell($this->d, 500);
    }

    private function move(Member $member, Member $parent, string $side, string $reason = 'Placed under the wrong leg at signup'): TestResponse
    {
        return $this->actingAs($this->admin, 'admin')->post(
            route('admin.tree.adjust', ['member' => $member->member_code]),
            ['new_parent' => $parent->member_code, 'side' => $side, 'reason' => $reason],
        );
    }

    public function test_moving_a_member_moves_their_downline_and_its_volume()
    {
        $this->move($this->c, $this->b, 'left')->assertSessionHas('success');

        $c = $this->c->fresh();
        $this->assertSame($this->b->id, $c?->placement_parent_id);
        $this->assertSame(PlacementSide::Left, $c?->placement_side);
        $this->assertNull($this->node($this->a)->left_child_id, 'Old slot is freed');
        $this->assertSame($this->c->id, $this->node($this->b)->left_child_id);
        $this->assertSame($this->c->id, $this->d->fresh()?->placement_parent_id, 'Downline stays attached');

        // ৳1,500 of BV left A and root's left leg, and arrived at B and root's right leg.
        $this->assertSame(0, $this->node($this->a)->left_volume);
        $this->assertSame(0, $this->node($this->a)->left_lifetime_volume);
        $this->assertSame(150_000, $this->node($this->b)->left_volume);
        $this->assertSame(0, $this->node($this->root)->left_volume);
        $this->assertSame(150_000, $this->node($this->root)->right_volume);
        $this->assertSame(150_000, $this->node($this->root)->right_lifetime_volume);

        // Inside the moved subtree nothing changes: C still has D's ৳500 on its left.
        $this->assertSame(50_000, $this->node($this->c)->left_volume);

        $this->assertLedgersConsistent();
        $this->assertSame(0, (int) VolumeLot::query()->where('member_id', $this->a->id)->sum('remaining'));
    }

    public function test_placement_adjustment_writes_a_complete_before_after_activity_entry()
    {
        $this->move($this->c, $this->b, 'left', 'Sponsor asked for the right leg at signup');

        $log = Activity::query()->where('description', 'Manual placement adjustment')->firstOrFail();

        $this->assertSame($this->admin->id, $log->causer_id);
        $this->assertSame($this->c->id, $log->subject_id);
        $this->assertSame('Sponsor asked for the right leg at signup', $log->properties['reason']);
        $this->assertEquals( // MySQL JSON reorders object keys
            ['parent' => $this->a->member_code, 'side' => 'left', 'upline' => [$this->a->member_code, $this->root->member_code]],
            $log->properties['before'],
        );
        $this->assertEquals( // MySQL JSON reorders object keys
            ['parent' => $this->b->member_code, 'side' => 'left', 'upline' => [$this->b->member_code, $this->root->member_code]],
            $log->properties['after'],
        );
        $this->assertSame(2, $log->properties['subtree_size']);
        $this->assertSame(2, $log->properties['sales_moved']);
        $this->assertSame(150_000, $log->properties['bv_moved']);
    }

    public function test_moves_are_refused_once_the_volume_has_been_matched()
    {
        $this->sell($this->b, 1_500); // root: L 1,500 vs R 1,500
        app(MatchingService::class)->runCycle(Carbon::parse('2026-09-20'));

        $this->move($this->c, $this->b, 'left')->assertSessionHas('error', fn (string $message) => str_contains($message, 'already been matched'));

        $this->assertSame($this->a->id, $this->c->fresh()?->placement_parent_id);
        $this->assertLedgersConsistent();
    }

    public function test_cannot_move_under_own_downline_into_a_taken_slot_or_without_a_reason()
    {
        $this->move($this->c, $this->d, 'right')->assertSessionHas('error', fn ($m) => str_contains($m, 'own downline'));
        $this->move($this->c, $this->root, 'right')->assertSessionHas('error', fn ($m) => str_contains($m, 'already taken'));
        $this->move($this->root, $this->b, 'left')->assertSessionHas('error', fn ($m) => str_contains($m, 'cannot be moved'));
        $this->move($this->c, $this->b, 'left', 'short')->assertSessionHasErrors('reason');

        $this->assertSame($this->a->id, $this->c->fresh()?->placement_parent_id);
        $this->assertSame(0, Activity::query()->where('description', 'Manual placement adjustment')->count());
    }

    public function test_only_manage_tree_admins_can_adjust()
    {
        $support = Admin::factory()->create()->assignRole('support');

        $this->actingAs($support, 'admin')
            ->post(route('admin.tree.adjust', ['member' => $this->c->member_code]), ['new_parent' => $this->b->member_code, 'side' => 'left', 'reason' => 'Trying without permission'])
            ->assertForbidden();
    }

    public function test_tree_browser_and_node_endpoint_work_for_any_member()
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.tree.index', ['member' => $this->a->member_code]))
            ->assertOk()
            ->assertSee('Subtree of');

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.tree.node', ['member' => $this->root->member_code]))
            ->assertOk()
            ->assertJsonPath('children.left.code', $this->a->member_code)
            ->assertJsonPath('children.left.children.left.code', $this->c->member_code);
    }
}
