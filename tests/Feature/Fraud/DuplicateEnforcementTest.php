<?php

namespace Tests\Feature\Fraud;

use App\Enums\MemberStatus;
use App\Enums\OrderStatus;
use App\Exceptions\DuplicateMemberException;
use App\Models\FraudFlag;
use App\Models\Member;
use App\Models\Order;
use App\Models\Package;
use App\Models\Sale;
use App\Models\User;
use App\Services\PlacementService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class DuplicateEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private Member $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = app(PlacementService::class)->activateMember(Member::factory()->create(['nid' => '1111111111']));
    }

    public function test_the_database_refuses_a_second_active_member_with_the_same_nid_or_phone()
    {
        $other = app(PlacementService::class)->activateMember(Member::factory()->create(['sponsor_id' => $this->root->id]));

        try {
            DB::table('members')->where('id', $other->id)->update(['nid' => '1111111111']);
            $this->fail('Duplicate active NID accepted');
        } catch (UniqueConstraintViolationException) {
        }

        try {
            DB::table('members')->where('id', $other->id)->update(['phone' => $this->root->phone]);
            $this->fail('Duplicate active phone accepted');
        } catch (UniqueConstraintViolationException) {
        }

        $this->assertNotNull($this->root->phone, 'Phone is mirrored onto members');
    }

    public function test_pending_and_suspended_duplicates_are_allowed()
    {
        // Two pending sign-ups with the root's NID: fine (rule #12 targets active members).
        Member::factory()->count(2)->create(['nid' => '1111111111']);

        // A suspended member's NID doesn't block a new active one.
        $this->root->forceFill(['status' => MemberStatus::Suspended])->save();
        $second = Member::factory()->create(['nid' => '1111111111', 'sponsor_id' => null]);
        DB::table('members')->where('id', $second->id)->update(['status' => 'active']);

        $this->assertSame(1, Member::query()->where('status', 'active')->where('nid', '1111111111')->count());
    }

    public function test_activating_a_duplicate_is_refused_and_leaves_no_trace()
    {
        $duplicate = Member::factory()->create(['nid' => '1111111111', 'sponsor_id' => $this->root->id]);
        $nextCode = DB::table('sequences')->where('name', 'member_code')->value('next_value');

        try {
            app(PlacementService::class)->activateMember($duplicate);
            $this->fail('Duplicate activated');
        } catch (DuplicateMemberException) {
        }

        $duplicate->refresh();
        $this->assertSame(MemberStatus::Pending, $duplicate->status);
        $this->assertNull($duplicate->member_code);
        $this->assertNull($duplicate->placement_parent_id);
        $this->assertNull($this->root->binaryNode()->firstOrFail()->left_child_id, 'Placement rolled back');
        $this->assertSame($nextCode, DB::table('sequences')->where('name', 'member_code')->value('next_value'), 'Code not consumed');
    }

    public function test_a_paid_duplicate_is_recorded_flagged_and_not_activated()
    {
        config(['payments.simulator' => true]);
        $duplicate = Member::factory()->create(['nid' => '1111111111', 'sponsor_id' => $this->root->id]);
        $package = Package::factory()->create();

        $this->actingAs($duplicate->user)->post(route('checkout.store'), ['package_id' => $package->id, 'gateway' => 'simulator']);
        $order = Order::query()->where('member_id', $duplicate->id)->firstOrFail();
        $this->get(route('payments.callback', ['gateway' => 'simulator', 'ref' => $order->payments()->firstOrFail()->gateway_ref, 'status' => 'success']));

        $this->assertSame(OrderStatus::Paid, $order->fresh()?->status, 'The money is real');
        $this->assertSame(MemberStatus::Pending, $duplicate->fresh()?->status);
        $this->assertSame(0, Sale::query()->where('order_id', $order->id)->count(), 'No sale, BV or commission');

        $flag = FraudFlag::query()->where('member_id', $duplicate->id)->firstOrFail();
        $this->assertSame(FraudFlag::ACTIVATION_BLOCKED_DUPLICATE, $flag->type);
        $this->assertTrue($flag->subject->is($order));
        $this->assertTrue(Activity::query()->where('description', 'like', 'Payment received but activation blocked%')->exists());
    }

    public function test_registration_still_rejects_a_nid_or_mobile_already_on_an_active_member()
    {
        $this->post(route('register.store'), [
            'name' => 'Copycat', 'email' => 'copy@example.com', 'phone' => (string) $this->root->phone, 'nid' => '1111111111',
            'address' => 'Dhaka', 'sponsor_code' => $this->root->member_code, 'preferred_side' => 'left',
            'package_id' => Package::factory()->create()->id, 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasErrors(['nid', 'phone']);

        $this->assertSame(0, User::query()->where('email', 'copy@example.com')->count());
    }
}
