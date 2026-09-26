<?php

namespace Tests\Feature\Ranks;

use App\Enums\CommissionType;
use App\Enums\MemberStatus;
use App\Enums\PlacementSide;
use App\Enums\WalletTransactionType;
use App\Models\Bonus;
use App\Models\BonusRule;
use App\Models\Commission;
use App\Models\Member;
use App\Models\Rank;
use App\Models\RankAchievement;
use App\Models\WalletTransaction;
use App\Services\BonusService;
use App\Services\RankService;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\BuildsNetwork;
use Tests\TestCase;

/**
 * Seeded ranks: Member (0) → Bronze (personal ৳5,000, team ৳50,000, 2 active,
 * bonus ৳1,000) → Silver (৳10,000, ৳200,000, 5 active, bonus ৳3,000) → …
 */
#[Group('rule-11')]
class RankAndBonusTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Member $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCommissionRules();
        BonusRule::query()->update(['is_active' => false]); // rank tests start without threshold bonuses

        $this->root = $this->root();
        $left = $this->join($this->root, PlacementSide::Left);
        $right = $this->join($this->root, PlacementSide::Right);

        // Bronze: personal ৳5,000 + team ৳50,000 + 2 active.
        $this->sell($this->root, 5_000);
        $this->sell($left, 25_000);
        $this->sell($right, 25_000);
    }

    private function rank(string $name): Rank
    {
        return Rank::query()->where('name', $name)->firstOrFail();
    }

    public function test_crossing_a_threshold_promotes_exactly_once_and_pays_the_bonus_once()
    {
        $ranks = app(RankService::class);
        $before = $this->balance($this->root);

        $first = $ranks->evaluate($this->root);
        $second = $ranks->evaluate($this->root->fresh());

        $this->assertCount(2, $first, 'Member (base) and Bronze are both recorded on first evaluation');
        $this->assertSame([], $second, 'Running evaluate() again changes nothing');
        $this->assertSame('Bronze', $this->root->fresh()?->currentRank?->name);
        $this->assertSame(1, RankAchievement::query()->where('member_id', $this->root->id)->where('rank_id', $this->rank('Bronze')->id)->count());
        $this->assertSame($before + 100_000, $this->balance($this->root), 'Bronze bonus ৳1,000 paid once');
        $this->assertLedgersConsistent();
    }

    public function test_rank_bonus_appears_correctly_in_wallet_history()
    {
        app(RankService::class)->evaluate($this->root);

        $commission = Commission::query()->where('member_id', $this->root->id)->where('type', CommissionType::Rank)->firstOrFail();
        $this->assertSame(100_000, $commission->amount);
        $this->assertSame('Rank bonus: Bronze', $commission->description);

        $credit = WalletTransaction::query()->where('type', WalletTransactionType::RankBonus)->firstOrFail();
        $this->assertSame(100_000, $credit->amount);
        $this->assertTrue($credit->reference->is($commission));

        $this->actingAs($this->root->user)
            ->get(route('wallet.index', ['type' => 'rank_bonus']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions.data', 1)
                ->where('transactions.data.0.amount', '+৳1,000.00')
                ->where('transactions.data.0.reference', 'Rank commission #'.$commission->id));

        $this->actingAs($this->root->user)
            ->get(route('income.index', ['tab' => 'rank']))
            ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1)->where('net', '৳1,000.00'));
    }

    public function test_skipping_ranks_records_and_pays_each_rank_once()
    {
        $this->rank('Silver')->update(['min_personal_sales' => 0, 'min_team_sales' => 0, 'min_active_team' => 0]);

        $new = app(RankService::class)->evaluate($this->root);

        $this->assertSame(['Member', 'Bronze', 'Silver'], collect($new)->map(fn ($a) => $a->rank->name)->all());
        $this->assertSame('Silver', $this->root->fresh()?->currentRank?->name);
        $this->assertSame(100_000 + 300_000, (int) Commission::query()->where('type', CommissionType::Rank)->sum('amount'));
    }

    public function test_every_threshold_must_be_met()
    {
        $this->rank('Bronze')->update(['min_active_team' => 3]); // only 2 active below root

        app(RankService::class)->evaluate($this->root);

        $this->assertSame('Member', $this->root->fresh()?->currentRank?->name);
        $this->assertSame(0, (int) Commission::query()->where('type', CommissionType::Rank)->sum('amount'));
    }

    public function test_ranks_are_never_taken_away()
    {
        app(RankService::class)->evaluate($this->root);

        $sale = $this->root->sales()->firstOrFail(); // the ৳5,000 personal sale
        app(RefundService::class)->refund($sale, 'returned');

        app(RankService::class)->evaluate($this->root->fresh());

        $this->assertSame('Bronze', $this->root->fresh()?->currentRank?->name);
        $this->assertSame(100_000, (int) Commission::query()->where('type', CommissionType::Rank)->sum('amount'));
    }

    public function test_suspended_members_are_not_evaluated()
    {
        $this->root->forceFill(['status' => MemberStatus::Suspended])->save();

        $this->assertSame([], app(RankService::class)->evaluate($this->root));
    }

    public function test_leadership_and_sales_threshold_bonuses_pay_once_each()
    {
        BonusRule::query()->delete();
        $leadership = BonusRule::query()->create(['type' => 'leadership', 'name' => 'Lead 2', 'threshold' => 2, 'amount' => 50_000]);
        $sales = BonusRule::query()->create(['type' => 'sales', 'name' => 'Sell ৳5k', 'threshold' => 500_000, 'amount' => 20_000]);
        BonusRule::query()->create(['type' => 'sales', 'name' => 'Sell ৳1M', 'threshold' => 100_000_000, 'amount' => 999_900]);
        BonusRule::query()->create(['type' => 'leadership', 'name' => 'Inactive', 'threshold' => 1, 'amount' => 1, 'is_active' => false]);

        $before = $this->balance($this->root);
        $bonuses = app(BonusService::class);

        $paid = $bonuses->evaluateThresholds($this->root);
        $again = $bonuses->evaluateThresholds($this->root);

        $this->assertEqualsCanonicalizing([$leadership->id, $sales->id], collect($paid)->pluck('bonus_rule_id')->all());
        $this->assertSame([], $again);
        $this->assertSame($before + 70_000, $this->balance($this->root));
        $this->assertSame(1, WalletTransaction::query()->where('type', WalletTransactionType::LeadershipBonus)->count());
        $this->assertSame(1, WalletTransaction::query()->where('type', WalletTransactionType::SalesBonus)->count());

        $this->actingAs($this->root->user)
            ->get(route('income.index', ['tab' => 'other']))
            ->assertInertia(fn (Assert $page) => $page->has('rows.data', 2)->where('net', '৳700.00'));
        $this->assertLedgersConsistent();
    }

    public function test_the_nightly_command_promotes_and_pays_threshold_bonuses()
    {
        BonusRule::query()->where('name', 'like', 'Sales: ৳50,000%')->update(['is_active' => true]); // ৳50,000 personal — not met

        $this->artisan('ranks:evaluate')->assertSuccessful();
        $this->artisan('ranks:evaluate')->assertSuccessful(); // idempotent

        $this->assertSame('Bronze', $this->root->fresh()?->currentRank?->name);
        $this->assertSame(1, RankAchievement::query()->where('rank_id', $this->rank('Bronze')->id)->count());
        $this->assertSame(0, Bonus::query()->count());
        $this->assertTrue(Activity::query()->where('description', 'Promoted to Bronze')->exists());
    }

    public function test_member_dashboard_shows_rank_and_progress_to_the_next()
    {
        app(RankService::class)->evaluate($this->root);

        $this->actingAs($this->root->user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('rank.current', 'Bronze')
                ->where('rank.next', 'Silver')
                ->has('rank.requirements', 3)
                ->where('rank.requirements.0.have', '৳5,000.00')
                ->where('rank.requirements.0.need', '৳10,000.00')
                ->where('rank.requirements.0.percent', 50)
                ->where('rank.requirements.2.label', 'Active team')
                ->where('rank.requirements.2.met', false));
    }
}
