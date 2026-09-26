<?php

namespace Tests\Feature\Auth;

use App\Enums\MemberStatus;
use App\Enums\PlacementSide;
use App\Models\Member;
use App\Models\Package;
use App\Models\User;
use App\Services\PlacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('rule-1')]
#[Group('rule-12')]
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Member $sponsor;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->package = Package::factory()->create();
        $this->sponsor = app(PlacementService::class)->activateMember(Member::factory()->create());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Rahim Uddin',
            'email' => 'rahim@example.com',
            'phone' => '01712-345678',
            'nid' => '1234567890',
            'address' => 'House 1, Road 2, Dhaka',
            'sponsor_code' => $this->sponsor->member_code,
            'preferred_side' => 'right',
            'package_id' => $this->package->id,
            'password' => 'password',
            'password_confirmation' => 'password',
            ...$overrides,
        ];
    }

    public function test_registration_screen_can_be_rendered()
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Register')
                ->has('packages', Package::query()->where('is_active', true)->count())
                ->where('sponsorCode', null));
    }

    public function test_referral_link_prefills_the_sponsor_code()
    {
        $this->get(route('register', ['ref' => 'mbr-100001']))
            ->assertInertia(fn (Assert $page) => $page->where('sponsorCode', 'MBR-100001'));
    }

    public function test_new_users_register_as_pending_members_under_their_sponsor()
    {
        $response = $this->post(route('register.store'), $this->payload());

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'rahim@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('member'));
        $this->assertSame('+8801712345678', $user->phone);
        $this->assertSame('127.0.0.1', $user->ip_registered);

        $member = $user->member()->firstOrFail();
        $this->assertSame(MemberStatus::Pending, $member->status);
        $this->assertSame($this->sponsor->id, $member->sponsor_id);
        $this->assertSame(PlacementSide::Right, $member->preferred_side);
        $this->assertSame($this->package->id, $member->package_id);
        $this->assertSame('1234567890', $member->nid);

        // Not placed and no code until payment activates them.
        $this->assertNull($member->member_code);
        $this->assertNull($member->placement_parent_id);
        $this->assertNull($member->binaryNode()->first());
    }

    public function test_dashboard_shows_pending_status()
    {
        $this->post(route('register.store'), $this->payload());

        $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('member.status', 'pending')
            ->where('member.code', null));
    }

    public function test_mobile_already_used_by_an_active_member_is_rejected()
    {
        $this->sponsor->user->update(['phone' => '+8801712345678']);

        $this->post(route('register.store'), $this->payload(['phone' => '8801712345678']))
            ->assertSessionHasErrors('phone');

        $this->assertGuest();
        $this->assertSame(1, Member::query()->count());
    }

    public function test_nid_already_used_by_an_active_member_is_rejected()
    {
        $this->sponsor->update(['nid' => '1234567890']);

        $this->post(route('register.store'), $this->payload(['nid' => '1234-567-890']))
            ->assertSessionHasErrors('nid');

        $this->assertGuest();
    }

    public function test_mobile_and_nid_of_a_pending_member_do_not_block_registration()
    {
        // Rule #12 targets duplicates among *active* members; an abandoned pending signup must not lock someone out.
        $pending = Member::factory()->create(['nid' => '1234567890']);
        $pending->user->update(['phone' => '+8801712345678']);

        $this->post(route('register.store'), $this->payload())->assertSessionHasNoErrors();
    }

    public function test_unknown_or_inactive_sponsor_is_rejected()
    {
        $this->post(route('register.store'), $this->payload(['sponsor_code' => 'MBR-999999']))
            ->assertSessionHasErrors('sponsor_code');

        $suspended = Member::factory()->active()->suspended()->create();

        $this->post(route('register.store'), $this->payload(['sponsor_code' => $suspended->member_code]))
            ->assertSessionHasErrors('sponsor_code');
    }

    public function test_invalid_fields_are_rejected()
    {
        $this->post(route('register.store'), $this->payload([
            'phone' => '0171234',
            'nid' => '12345',
            'preferred_side' => 'middle',
            'package_id' => Package::factory()->inactive()->create()->id,
        ]))->assertSessionHasErrors(['phone', 'nid', 'preferred_side', 'package_id']);
    }

    public function test_sponsor_lookup_returns_only_the_name_of_active_members()
    {
        $this->getJson(route('sponsors.show', strtolower((string) $this->sponsor->member_code)))
            ->assertOk()
            ->assertExactJson(['code' => $this->sponsor->member_code, 'name' => $this->sponsor->user->name]);

        $this->getJson(route('sponsors.show', 'MBR-999999'))->assertNotFound();
    }
}
