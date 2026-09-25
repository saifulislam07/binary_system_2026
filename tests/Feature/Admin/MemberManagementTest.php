<?php

namespace Tests\Feature\Admin;

use App\Enums\MemberStatus;
use App\Models\Admin;
use App\Models\Member;
use App\Models\Package;
use App\Services\PlacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class MemberManagementTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->superAdmin()->create();
        $root = app(PlacementService::class)->activateMember(Member::factory()->create());
        $this->member = app(PlacementService::class)->activateMember(Member::factory()->create(['sponsor_id' => $root->id]));
    }

    public function test_admin_without_manage_members_gets_403_on_member_edit()
    {
        $finance = Admin::factory()->create()->assignRole('finance');

        $this->actingAs($finance, 'admin')->get(route('admin.members.edit', $this->member))->assertForbidden();
        $this->actingAs($finance, 'admin')
            ->put(route('admin.members.update', $this->member), ['name' => 'Hacked', 'email' => 'x@example.com', 'phone' => '01712345678', 'nid' => '1234567890', 'address' => 'x'])
            ->assertForbidden();

        $this->assertNotSame('Hacked', $this->member->user->fresh()?->name);
    }

    public function test_list_searches_and_filters()
    {
        $this->member->user->update(['name' => 'Karim Sheikh']);
        Member::factory()->count(3)->create(); // pending noise

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.members.index', ['q' => 'karim']))
            ->assertOk()
            ->assertSee('Karim Sheikh')
            ->assertViewHas('members', fn ($members) => $members->total() === 1);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.members.index', ['status' => 'pending']))
            ->assertViewHas('members', fn ($members) => $members->total() === 3);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.members.index', ['q' => $this->member->member_code]))
            ->assertViewHas('members', fn ($members) => $members->total() === 1);
    }

    public function test_profile_page_shows_sponsor_placement_and_wallet()
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.members.show', $this->member))
            ->assertOk()
            ->assertSee($this->member->member_code)
            ->assertSee($this->member->sponsor->member_code)
            ->assertSee('Wallet balance');
    }

    public function test_update_normalizes_and_logs_old_and_new_values()
    {
        $oldEmail = $this->member->user->email;

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.members.update', $this->member), [
                'name' => $this->member->user->name,
                'email' => 'new@example.com',
                'phone' => '01812-345678',
                'nid' => '1234-5678-90',
                'address' => 'New address',
            ])
            ->assertRedirect(route('admin.members.show', $this->member))
            ->assertSessionHasNoErrors();

        $this->member->refresh()->load('user');
        $this->assertSame('+8801812345678', $this->member->user->phone);
        $this->assertSame('1234567890', $this->member->nid);

        $log = Activity::query()->where('description', 'Member profile updated by admin')->firstOrFail();
        $this->assertSame($this->admin->id, $log->causer_id);
        $this->assertSame($oldEmail, $log->properties['old']['email']);
        $this->assertSame('new@example.com', $log->properties['attributes']['email']);
        $this->assertArrayNotHasKey('name', $log->properties['attributes'], 'Unchanged fields are not logged');
    }

    public function test_update_rejects_a_phone_or_nid_owned_by_another_active_member()
    {
        $other = app(PlacementService::class)->activateMember(Member::factory()->create(['sponsor_id' => $this->member->id]));
        $other->update(['nid' => '9999999999']);
        $other->user->update(['phone' => '+8801999999999']);

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.members.update', $this->member), [
                'name' => 'X', 'email' => $this->member->user->email,
                'phone' => '01999999999', 'nid' => '9999999999', 'address' => 'x',
            ])
            ->assertSessionHasErrors(['phone', 'nid']);
    }

    public function test_suspend_and_reinstate_require_reasons_and_are_logged()
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.members.suspend', $this->member), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.members.suspend', $this->member), ['reason' => 'Chargeback investigation'])
            ->assertSessionHas('success');
        $this->assertSame(MemberStatus::Suspended, $this->member->fresh()?->status);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.members.reinstate', $this->member), ['reason' => 'Cleared'])
            ->assertSessionHas('success');
        $this->assertSame(MemberStatus::Active, $this->member->fresh()?->status);

        $this->assertSame(
            ['Chargeback investigation', 'Cleared'],
            Activity::query()->whereIn('description', ['Member suspended', 'Member reinstated'])->orderBy('id')->get()->map(fn ($a) => $a->properties['reason'])->all(),
        );
    }

    public function test_a_member_who_never_paid_cannot_be_reinstated()
    {
        $pending = Member::factory()->create(['status' => MemberStatus::Suspended]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.members.reinstate', $pending), ['reason' => 'x'])
            ->assertSessionHas('error');

        $this->assertSame(MemberStatus::Suspended, $pending->fresh()?->status);
    }

    public function test_change_package_relabels_and_logs_without_creating_a_sale()
    {
        $premium = Package::query()->where('name', 'Premium')->firstOrFail();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.members.package', $this->member), ['package_id' => $premium->id, 'reason' => 'Upgrade paid in cash'])
            ->assertSessionHas('success');

        $this->assertSame($premium->id, $this->member->fresh()?->package_id);
        $this->assertSame(0, $this->member->sales()->count());
        $this->assertSame('Premium', Activity::query()->where('description', 'Member package changed by admin')->firstOrFail()->properties['attributes']['package']);
    }
}
