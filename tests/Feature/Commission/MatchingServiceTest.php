<?php

namespace Tests\Feature\Commission;

use App\Enums\CommissionCycleStatus;
use App\Enums\CommissionType;
use App\Enums\MemberStatus;
use App\Enums\PayoutStatus;
use App\Enums\PlacementSide;
use App\Exceptions\MatchingException;
use App\Models\Commission;
use App\Models\CommissionCycle;
use App\Models\CommissionRule;
use App\Models\Member;
use App\Models\TeamVolume;
use App\Models\VolumeConsumption;
use App\Models\VolumeLot;
use App\Services\MatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsNetwork;
use Tests\TestCase;

#[Group('rule-5')]
#[Group('rule-6')]
class MatchingServiceTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Member $root;

    private Member $left;

    private Member $right;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCommissionRules();
        $this->root = $this->root();
        $this->left = $this->join($this->root, PlacementSide::Left);
        $this->right = $this->join($this->root, PlacementSide::Right);
    }

    private function runCycle(string $date = '2026-09-20'): void
    {
        app(MatchingService::class)->runCycle(Carbon::parse($date));
    }

    /**
     * Root's binary commission rows only (referral bonuses excluded).
     */
    private function binaryPaid(Member $member): int
    {
        return (int) Commission::query()
            ->where('member_id', $member->id)
            ->where('type', CommissionType::Binary)
            ->where('status', PayoutStatus::Paid)
            ->sum('amount');
    }

    public function test_matching_pays_rate_times_min_of_left_and_right()
    {
        $this->sell($this->left, 3_000);
        $this->sell($this->right, 1_000);
        $walletBefore = $this->balance($this->root);

        $this->runCycle();

        $tv = TeamVolume::query()->where('member_id', $this->root->id)->firstOrFail();
        $this->assertSame(300_000, $tv->left_volume);
        $this->assertSame(100_000, $tv->right_volume);
        $this->assertSame(100_000, $tv->matched_volume);           // min(3000, 1000) BV
        $this->assertSame(10_000, $tv->gross_commission);          // 10% of 1000 BV = ৳100
        $this->assertSame(10_000, $tv->paid_commission);
        $this->assertSame(10_000, $this->binaryPaid($this->root));
        $this->assertSame($walletBefore + 10_000, $this->balance($this->root));
        $this->assertLedgersConsistent();
    }

    public function test_carry_forward_keeps_the_unmatched_side_when_enabled()
    {
        $this->sell($this->left, 3_000);
        $this->sell($this->right, 1_000);

        $this->runCycle();

        $node = $this->node($this->root);
        $this->assertSame(200_000, $node->left_volume);
        $this->assertSame(0, $node->right_volume);
        $this->assertSame(200_000, $node->left_volume_carry);
        $this->assertSame(200_000, TeamVolume::query()->firstOrFail()->carried_left);

        // Next cycle, new right volume matches against the carried left.
        $this->sell($this->right, 2_000);
        $this->runCycle('2026-09-21');

        $this->assertSame(0, $this->node($this->root)->left_volume);
        $this->assertSame(30_000, $this->binaryPaid($this->root)); // 1000 + 2000 BV matched in total
        $this->assertLedgersConsistent();
    }

    public function test_unmatched_side_is_zeroed_when_carry_forward_is_disabled()
    {
        $this->rule(CommissionRule::CARRY_FORWARD_ENABLED, '0');
        $this->sell($this->left, 3_000);
        $this->sell($this->right, 1_000);

        $this->runCycle();

        $node = $this->node($this->root);
        $tv = TeamVolume::query()->where('member_id', $this->root->id)->firstOrFail();
        $this->assertSame(0, $node->left_volume);
        $this->assertSame(0, $node->right_volume);
        $this->assertSame(200_000, $tv->flushed_left);
        $this->assertSame(0, $tv->carried_left);
        $this->assertFalse($tv->carry_forward_enabled);
        $this->assertSame(200_000, (int) VolumeConsumption::query()->where('kind', 'flushed')->sum('bv'));
        $this->assertSame(10_000, $this->binaryPaid($this->root));
        $this->assertLedgersConsistent();
    }

    public function test_one_sided_volume_is_flushed_too_when_carry_forward_is_disabled()
    {
        $this->rule(CommissionRule::CARRY_FORWARD_ENABLED, '0');
        $this->sell($this->left, 3_000);

        $this->runCycle();

        $this->assertSame(0, $this->node($this->root)->left_volume);
        $this->assertSame(0, $this->binaryPaid($this->root));
    }

    public function test_daily_cap_voids_the_excess_when_overflow_is_void()
    {
        // 10% of ৳100,000 matched = ৳10,000 against a ৳5,000 daily cap.
        $this->sell($this->left, 100_000);
        $this->sell($this->right, 100_000);
        $before = $this->balance($this->root);

        $this->runCycle();

        $tv = TeamVolume::query()->where('member_id', $this->root->id)->firstOrFail();
        $this->assertSame(1_000_000, $tv->gross_commission);
        $this->assertSame(500_000, $tv->paid_commission);
        $this->assertSame(500_000, $tv->overflow_commission);
        $this->assertSame('void', $tv->overflow_action);
        $this->assertSame($before + 500_000, $this->balance($this->root));

        $voided = Commission::query()->where('member_id', $this->root->id)->where('status', PayoutStatus::Voided)->firstOrFail();
        $this->assertSame(500_000, $voided->amount);
        $this->assertSame(0, $this->node($this->root)->deferred_commission);
        $this->assertLedgersConsistent();
    }

    public function test_daily_cap_carries_the_excess_forward_when_configured()
    {
        $this->rule(CommissionRule::CAP_OVERFLOW_BEHAVIOR, 'carry_forward');
        $this->sell($this->left, 100_000);
        $this->sell($this->right, 100_000);
        $before = $this->balance($this->root);

        $this->runCycle('2026-09-20');

        $this->assertSame(500_000, $this->node($this->root)->deferred_commission);
        $this->assertSame($before + 500_000, $this->balance($this->root));
        $this->assertSame(0, Commission::query()->where('status', PayoutStatus::Voided)->count());

        // Next day: no new volume, but the deferred ৳5,000 is released within the fresh daily cap.
        $this->runCycle('2026-09-21');

        $this->assertSame(0, $this->node($this->root)->deferred_commission);
        $this->assertSame($before + 1_000_000, $this->balance($this->root));
        $this->assertSame(500_000, TeamVolume::query()->where('member_id', $this->root->id)->latest('id')->firstOrFail()->deferred_released);
        $this->assertLedgersConsistent();
    }

    public function test_weekly_cap_counts_commissions_already_paid_that_week()
    {
        $this->rule(CommissionRule::DAILY_CAP, '0');           // no daily cap
        $this->rule(CommissionRule::WEEKLY_CAP, '2000000');    // ৳20,000

        // Sat 2026-09-19 starts the week (week_starts_on = Saturday).
        $this->sell($this->left, 150_000);
        $this->sell($this->right, 150_000);
        $this->runCycle('2026-09-19');                          // ৳15,000 paid

        $this->sell($this->left, 150_000);
        $this->sell($this->right, 150_000);
        $this->runCycle('2026-09-20');                          // only ৳5,000 room left

        $tv = TeamVolume::query()->where('member_id', $this->root->id)->latest('id')->firstOrFail();
        $this->assertSame(1_500_000, $tv->gross_commission);
        $this->assertSame(500_000, $tv->paid_commission);
        $this->assertSame(2_000_000, $this->binaryPaid($this->root));

        // The next week has fresh room.
        $this->sell($this->left, 10_000);
        $this->sell($this->right, 10_000);
        $this->runCycle('2026-09-26');
        $this->assertSame(2_100_000, $this->binaryPaid($this->root));
    }

    public function test_monthly_cap_limits_payouts_across_weeks()
    {
        $this->rule(CommissionRule::DAILY_CAP, '0');
        $this->rule(CommissionRule::WEEKLY_CAP, '0');
        $this->rule(CommissionRule::MONTHLY_CAP, '5000000'); // ৳50,000

        $this->sell($this->left, 400_000);
        $this->sell($this->right, 400_000);
        $this->runCycle('2026-09-05'); // ৳40,000

        $this->sell($this->left, 400_000);
        $this->sell($this->right, 400_000);
        $this->runCycle('2026-09-20'); // only ৳10,000 room

        $this->assertSame(5_000_000, $this->binaryPaid($this->root));
    }

    public function test_running_the_same_cycle_twice_pays_once()
    {
        $this->sell($this->left, 1_000);
        $this->sell($this->right, 1_000);

        $this->runCycle();
        $this->runCycle();

        $this->assertSame(10_000, $this->binaryPaid($this->root));
        $this->assertSame(CommissionCycleStatus::Closed, TeamVolume::query()->firstOrFail()->cycle->status);
    }

    public function test_an_interrupted_cycle_can_be_resumed_without_double_paying()
    {
        $this->sell($this->left, 1_000);
        $this->sell($this->right, 1_000);

        $service = app(MatchingService::class);
        $date = Carbon::parse('2026-09-20');
        $cycle = CommissionCycle::query()->create(['cycle_date' => '2026-09-20', 'status' => CommissionCycleStatus::Running]);

        // Simulate a crash after this member was processed but before the cycle closed.
        $service->processMember($this->root->id, $cycle, $date, $service->rules());
        $service->runCycle($date);

        $this->assertSame(10_000, $this->binaryPaid($this->root));
        $this->assertSame(1, TeamVolume::query()->where('member_id', $this->root->id)->count());
    }

    public function test_cannot_run_a_cycle_before_an_already_closed_one()
    {
        $this->runCycle('2026-09-20');

        $this->expectException(MatchingException::class);
        $this->runCycle('2026-09-19');
    }

    public function test_suspended_members_are_not_matched_or_paid()
    {
        $this->sell($this->left, 1_000);
        $this->sell($this->right, 1_000);
        $this->root->forceFill(['status' => MemberStatus::Suspended])->save();

        $this->runCycle();

        $this->assertSame(0, $this->binaryPaid($this->root));
        $this->assertSame(100_000, $this->node($this->root)->left_volume, 'Volume stays until reinstated');
    }

    public function test_matching_consumes_the_oldest_volume_first()
    {
        $older = $this->sell($this->left, 1_000);
        $newer = $this->sell($this->left, 1_000);
        $this->sell($this->right, 1_000);

        $this->runCycle();

        $remaining = fn ($sale) => (int) VolumeLot::query()->where('sale_id', $sale->id)->where('member_id', $this->root->id)->value('remaining');
        $this->assertSame(0, $remaining($older));
        $this->assertSame(100_000, $remaining($newer));
    }

    public function test_artisan_command_runs_the_cycle()
    {
        $this->sell($this->left, 1_000);
        $this->sell($this->right, 1_000);

        $this->artisan('commission:run', ['date' => '2026-09-20'])->assertSuccessful();

        $this->assertSame(10_000, $this->binaryPaid($this->root));
    }
}
