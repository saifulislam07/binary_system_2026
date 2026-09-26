<?php

namespace Tests\Feature\Performance;

use App\Enums\PlacementSide;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalMethodType;
use App\Models\Admin;
use App\Models\KycDocument;
use App\Models\Member;
use App\Services\MatchingService;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsNetwork;
use Tests\TestCase;

/**
 * No N+1: every dashboard, tree and list endpoint must run the same number
 * of queries whether the network is small or twice as big. The base network
 * is already deeper than the tree views' depth cap, so growing it only adds
 * rows, never levels the views would render.
 */
class QueryBudgetTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Admin $admin;

    private Member $root;

    /** @var list<Member> */
    private array $members = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCommissionRules();
        $this->admin = Admin::factory()->superAdmin()->create();
        $this->root = $this->root();
        $this->members = [$this->root];
        $this->grow(32);
    }

    /**
     * Add members level by level (alternating sides), each buying a package,
     * plus the side data the list pages show.
     */
    private function grow(int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $sponsor = $this->members[intdiv(count($this->members) - 1, 2)];
            $member = $this->join($sponsor, count($this->members) % 2 === 1 ? PlacementSide::Left : PlacementSide::Right);
            $this->sell($member, 1_000);
            $this->members[] = $member;

            if ($i % 4 === 0) {
                app(WalletService::class)->credit($member, 300_000, WalletTransactionType::ReferralBonus);
                app(WithdrawalService::class)->request($member, 100_000, WithdrawalMethodType::MobileBanking, ['provider' => 'bkash', 'mobile_number' => '+8801712345678']);
                KycDocument::factory()->create(['member_id' => $member->id]);
            }
        }

        app(MatchingService::class)->runCycle(Carbon::parse('2026-09-20')->addDays(count($this->members)));
    }

    /**
     * @return array<string, array{0: 'web'|'admin', 1: callable(self): string}>
     */
    private function endpoints(): array
    {
        return [
            'member dashboard' => ['web', fn () => route('dashboard')],
            'team page' => ['web', fn () => route('team.index')],
            'team tree json' => ['web', fn () => route('team.tree', ['member' => $this->root->member_code, 'depth' => 4])],
            'income' => ['web', fn () => route('income.index')],
            'wallet' => ['web', fn () => route('wallet.index')],
            'withdrawals' => ['web', fn () => route('withdrawals.index')],
            'notifications' => ['web', fn () => route('notifications.index')],
            'notification bell' => ['web', fn () => route('notifications.recent')],
            'admin dashboard' => ['admin', fn () => route('admin.dashboard')],
            'admin members' => ['admin', fn () => route('admin.members.index')],
            'admin member profile' => ['admin', fn () => route('admin.members.show', $this->root)],
            'admin tree node' => ['admin', fn () => route('admin.tree.node', $this->root->member_code)],
            'admin sales' => ['admin', fn () => route('admin.sales.index')],
            'admin withdrawals' => ['admin', fn () => route('admin.withdrawals.index')],
            'admin kyc' => ['admin', fn () => route('admin.kyc.index')],
            'admin fraud flags' => ['admin', fn () => route('admin.fraud.index')],
            'admin rank report' => ['admin', fn () => route('admin.reports.ranks')],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function measure(): array
    {
        $counts = [];

        foreach ($this->endpoints() as $name => [$guard, $url]) {
            $guard === 'web' ? $this->actingAs($this->root->user) : $this->actingAs($this->admin, 'admin');

            $target = $url($this);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson($target)->assertOk();
            $counts[$name] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }

        return $counts;
    }

    public function test_the_commission_cycle_costs_a_bounded_number_of_queries_per_member()
    {
        // More sales on both legs so most members have something to match.
        foreach (array_slice($this->members, 1) as $member) {
            $this->sell($member, 1_000);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $cycle = app(MatchingService::class)->runCycle(Carbon::parse('2026-12-31'));
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $processed = $cycle->teamVolumes()->count();
        $this->assertGreaterThan(10, $processed);
        $this->assertLessThanOrEqual(30 * $processed + 20, $queries, "{$queries} queries for {$processed} members");
    }

    public function test_query_counts_do_not_grow_with_the_network()
    {
        $this->measure(); // warm-up: permission and settings caches
        $small = $this->measure();

        $this->grow(32);
        $big = $this->measure();

        $this->assertSame($small, $big, 'An endpoint runs more queries on a bigger network (N+1).');

        foreach ($big as $name => $count) {
            $this->assertLessThanOrEqual(40, $count, "{$name} runs {$count} queries");
        }
    }
}
