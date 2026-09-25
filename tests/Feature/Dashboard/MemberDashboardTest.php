<?php

namespace Tests\Feature\Dashboard;

use App\Models\Member;
use App\Models\User;
use App\Services\MatchingService;
use Database\Seeders\DemoNetworkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Uses the documented demo network (DemoNetworkSeeder) so every number is
 * known in advance:
 *   - the root (MBR-100001) bought Basic (৳1,000);
 *   - left leg: 12 members, 138,000 BV; right leg: 7 members, 66,000 BV;
 *   - root sponsors MBR-100002..100007 → ৳2,800 referral (5% of ৳56,000);
 *   - one cycle matches 66,000 BV → ৳6,600 gross, capped at ৳5,000 daily.
 */
class MemberDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Member $root;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-20 12:00:00');
        $this->seed(DemoNetworkSeeder::class);
        app(MatchingService::class)->runCycle(Carbon::parse('2026-09-20'));

        $this->root = Member::query()->where('member_code', 'MBR-100001')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function member(string $code): Member
    {
        return Member::query()->where('member_code', $code)->firstOrFail();
    }

    public function test_overview_cards_show_the_seeded_members_known_numbers()
    {
        $this->actingAs($this->root->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('overview.totalIncome', '৳7,800.00')
                ->where('overview.available', '৳7,800.00')
                ->where('overview.personalSales', '৳1,000.00')
                ->where('overview.leftTeamBv', '138,000')
                ->where('overview.rightTeamBv', '66,000')
                ->where('overview.teamSize', 19)
                ->where('overview.activeTeam', 19)
                ->where('overview.leftTeam', ['total' => 12, 'active' => 12])
                ->where('overview.rightTeam', ['total' => 7, 'active' => 7])
                ->where('walletSummary.referral', '৳2,800.00')
                ->where('walletSummary.binary', '৳5,000.00'));
    }

    public function test_income_chart_has_six_daily_periods_with_income_in_the_right_one()
    {
        $this->actingAs($this->root->user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('overview.chart', 6)
                ->where('overview.chart.0.from', '2026-09-15')
                ->where('overview.chart.5.from', '2026-09-20')
                ->where('overview.chart.5.amount', 7_800)
                ->where('overview.chart.5.formatted', '৳7,800.00')
                ->where('overview.chart.4.amount', 0));
    }

    public function test_team_counts_reflect_an_inactive_downline_member()
    {
        $this->member('MBR-100020')->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($this->root->user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('overview.teamSize', 19)
                ->where('overview.activeTeam', 18)
                ->where('overview.leftTeam', ['total' => 12, 'active' => 11]));
    }

    public function test_pending_members_get_no_overview()
    {
        $pending = Member::factory()->create();

        $this->actingAs($pending->user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('overview', null)->where('member.status', 'pending'));
    }

    public function test_team_page_shows_sponsor_legs_and_two_levels_of_the_tree()
    {
        $this->actingAs($this->member('MBR-100004')->user)
            ->get(route('team.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('team/Index')
                ->where('sponsor.code', 'MBR-100001')                  // spillover: sponsor ≠ parent
                ->where('legs.left', ['total' => 3, 'active' => 3])   // 8, 16, 17
                ->where('legs.right', ['total' => 3, 'active' => 3])  // 9, 18, 19
                ->where('tree.code', 'MBR-100004')
                ->where('tree.side', 'left')
                ->where('tree.children.left.code', 'MBR-100008')
                ->where('tree.children.right.code', 'MBR-100009')
                ->where('tree.children.left.children.left.code', 'MBR-100016')
                ->where('tree.children.left.children.right.code', 'MBR-100017')
                // Third level is not shipped — loaded on demand.
                ->where('tree.children.left.children.left.children', null)
                ->where('tree.children.left.children.left.hasLeft', false));
    }

    public function test_tree_nodes_carry_status_package_and_team_volume_from_binary_nodes()
    {
        $this->actingAs($this->root->user)
            ->get(route('team.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('tree.code', 'MBR-100001')
                ->where('tree.package', 'Basic')
                ->where('tree.active', true)
                ->where('tree.leftBv', 138_000)
                ->where('tree.rightBv', 66_000)
                ->where('tree.teamBv', 204_000)
                ->where('tree.children.left.code', 'MBR-100002')
                ->where('tree.children.right.code', 'MBR-100003')
                ->where('tree.children.left.children.left.code', 'MBR-100004')
                ->where('tree.children.right.children.right.code', 'MBR-100007')
                ->where('tree.children.left.children.left.children', null)
                ->where('tree.children.left.children.left.hasLeft', true));
    }

    public function test_deeper_levels_load_lazily_from_the_api()
    {
        $this->actingAs($this->root->user)
            ->getJson(route('team.tree', ['member' => 'MBR-100004']))
            ->assertOk()
            ->assertJsonPath('code', 'MBR-100004')
            ->assertJsonPath('children.left.code', 'MBR-100008')
            ->assertJsonPath('children.right.code', 'MBR-100009')
            ->assertJsonPath('children.left.children.left.code', 'MBR-100016')
            ->assertJsonPath('children.right.children.right.code', 'MBR-100019');

        $this->actingAs($this->root->user)
            ->getJson(route('team.tree', ['member' => 'MBR-100010', 'depth' => 1]))
            ->assertJsonPath('children.left.code', 'MBR-100020')
            ->assertJsonPath('children.right', null)
            ->assertJsonPath('children.left.children', null);
    }

    public function test_members_cannot_open_branches_outside_their_downline()
    {
        $left = $this->member('MBR-100002');

        $this->actingAs($left->user)
            ->getJson(route('team.tree', ['member' => 'MBR-100003']))  // sibling leg
            ->assertForbidden();

        $this->actingAs($left->user)
            ->getJson(route('team.tree', ['member' => 'MBR-100001']))  // upline
            ->assertForbidden();

        $this->actingAs($left->user)
            ->getJson(route('team.tree', ['member' => 'MBR-100017']))  // own downline
            ->assertOk();
    }

    public function test_tree_depth_is_capped()
    {
        $response = $this->actingAs($this->root->user)
            ->getJson(route('team.tree', ['member' => 'MBR-100001', 'depth' => 50]));

        // MAX_TREE_DEPTH = 4: level 4 (MBR-100016) is loaded, its children are not.
        $response->assertJsonPath('children.left.children.left.children.left.children.left.code', 'MBR-100016');
        $response->assertJsonPath('children.left.children.left.children.left.children.left.children', null);
    }

    public function test_pending_members_are_sent_to_checkout_from_team_pages()
    {
        $pending = Member::factory()->create();

        $this->actingAs($pending->user)->get(route('team.index'))->assertRedirect(route('checkout.index'));
        $this->actingAs($pending->user)->get(route('income.index'))->assertRedirect(route('checkout.index'));
        $this->actingAs($pending->user)->get(route('referral.index'))->assertRedirect(route('checkout.index'));
        $this->actingAs(User::factory()->create())->get(route('team.index'))->assertForbidden();
    }

    public function test_income_tabs_list_commissions_with_sources_and_net_totals()
    {
        $this->actingAs($this->root->user)
            ->get(route('income.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('income/Index')
                ->where('tab', 'referral')
                ->has('rows.data', 6)
                ->where('net', '৳2,800.00')
                ->where('rows.data.0.source', fn (string $source) => str_starts_with($source, 'Sale by MBR-1000')));

        $this->actingAs($this->root->user)
            ->get(route('income.index', ['tab' => 'binary']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 2)                  // ৳5,000 paid + ৳1,600 voided above the cap
                ->where('net', '৳5,000.00')
                ->where('rows.data.0.source', 'Cycle 2026-09-20'));

        $this->actingAs($this->root->user)
            ->get(route('income.index', ['tab' => 'rank']))
            ->assertInertia(fn (Assert $page) => $page->has('rows.data', 0)->where('net', '৳0.00'));

        $this->actingAs($this->root->user)
            ->get(route('income.index', ['tab' => 'referral', 'from' => '2026-09-21']))
            ->assertInertia(fn (Assert $page) => $page->has('rows.data', 0));

        $this->actingAs($this->root->user)
            ->get(route('income.index', ['tab' => 'bogus']))
            ->assertSessionHasErrors('tab');
    }

    public function test_referral_page_has_the_link_and_share_urls()
    {
        $url = route('register', ['ref' => 'MBR-100001']);

        $this->actingAs($this->root->user)
            ->get(route('referral.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('referral/Index')
                ->where('code', 'MBR-100001')
                ->where('url', $url)
                ->where('shareLinks.facebook', 'https://www.facebook.com/sharer/sharer.php?u='.rawurlencode($url))
                ->where('shareLinks.whatsapp', fn (string $link) => str_starts_with($link, 'https://wa.me/?text=') && str_contains($link, rawurlencode($url)))
                ->where('referrals', ['total' => 6, 'active' => 6]));

        // The link actually prefills registration.
        $this->post(route('logout'));
        $this->get($url)->assertInertia(fn (Assert $page) => $page->where('sponsorCode', 'MBR-100001'));
    }
}
