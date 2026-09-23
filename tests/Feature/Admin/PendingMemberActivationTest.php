<?php

namespace Tests\Feature\Admin;

use App\Enums\MemberStatus;
use App\Models\Admin;
use App\Models\Member;
use App\Services\PlacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingMemberActivationTest extends TestCase
{
    use RefreshDatabase;

    private Member $pending;

    protected function setUp(): void
    {
        parent::setUp();

        $root = app(PlacementService::class)->activateMember(Member::factory()->create());
        $this->pending = Member::factory()->create(['sponsor_id' => $root->id]);
    }

    public function test_admin_with_manage_members_can_list_and_activate_pending_members()
    {
        $admin = Admin::factory()->superAdmin()->create();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.members.pending'))
            ->assertOk()
            ->assertSee($this->pending->user->email);

        $this->actingAs($admin, 'admin')
            ->from(route('admin.members.pending'))
            ->post(route('admin.members.activate', $this->pending))
            ->assertRedirect(route('admin.members.pending'))
            ->assertSessionHas('success');

        $this->assertSame(MemberStatus::Active, $this->pending->fresh()?->status);
    }

    public function test_admin_without_manage_members_gets_403()
    {
        $admin = Admin::factory()->create(); // no role, no permissions

        $this->actingAs($admin, 'admin')->get(route('admin.members.pending'))->assertForbidden();
        $this->actingAs($admin, 'admin')->post(route('admin.members.activate', $this->pending))->assertForbidden();

        $this->assertSame(MemberStatus::Pending, $this->pending->fresh()?->status);
    }
}
