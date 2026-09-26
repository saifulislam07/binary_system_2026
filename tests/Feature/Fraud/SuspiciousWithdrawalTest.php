<?php

namespace Tests\Feature\Fraud;

use App\Enums\PlacementSide;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalMethodType;
use App\Models\Admin;
use App\Models\FraudFlag;
use App\Models\Member;
use App\Models\Withdrawal;
use App\Services\FraudScanService;
use App\Services\MatchingService;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\BuildsNetwork;
use Tests\TestCase;

class SuspiciousWithdrawalTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private const BKASH = ['provider' => 'bkash', 'mobile_number' => '+8801712345678'];

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCommissionRules();
        $this->admin = Admin::factory()->superAdmin()->create();
    }

    private function withdraw(Member $member, int $amount): Withdrawal
    {
        return app(WithdrawalService::class)->request($member, $amount, WithdrawalMethodType::MobileBanking, self::BKASH);
    }

    public function test_a_suspicious_withdrawal_is_flagged_and_appears_in_the_admin_review_queue()
    {
        // Joined an hour ago; the wallet was topped up by a manual adjustment, not by earning.
        $member = Member::factory()->active()->create(['activated_at' => now()->subHour()]);
        app(WalletService::class)->credit($member, 300_000, WalletTransactionType::Adjustment);

        $withdrawal = $this->withdraw($member, 200_000);

        $flags = FraudFlag::query()->where('member_id', $member->id)->pluck('type')->all();
        $this->assertEqualsCanonicalizing([FraudFlag::RAPID_WITHDRAWAL, FraudFlag::WITHDRAWAL_EXCEEDS_EARNINGS], $flags);

        $excess = FraudFlag::query()->where('type', FraudFlag::WITHDRAWAL_EXCEEDS_EARNINGS)->firstOrFail();
        $this->assertTrue($excess->subject->is($withdrawal));
        $this->assertEquals(['withdrawn' => 200_000, 'earned' => 0, 'excess' => 200_000], $excess->details);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.fraud.index'))
            ->assertOk()
            ->assertSee($member->member_code)
            ->assertSee(FraudFlag::LABELS[FraudFlag::RAPID_WITHDRAWAL])
            ->assertSee(FraudFlag::LABELS[FraudFlag::WITHDRAWAL_EXCEEDS_EARNINGS])
            ->assertSee('Withdrawal #'.$withdrawal->id)
            ->assertViewHas('flags', fn ($page) => $page->total() === 2);
    }

    public function test_the_member_is_not_blocked_by_a_flag()
    {
        $member = Member::factory()->active()->create(['activated_at' => now()->subHour()]);
        app(WalletService::class)->credit($member, 300_000, WalletTransactionType::Adjustment);

        $withdrawal = $this->withdraw($member, 200_000);

        $this->assertSame('pending', $withdrawal->fresh()?->status->value);
        $this->assertSame(100_000, app(WalletService::class)->balance($member));
    }

    public function test_an_established_member_withdrawing_real_earnings_is_not_flagged()
    {
        $root = $this->root();
        $root->forceFill(['activated_at' => now()->subDays(30)])->save();
        $left = $this->join($root, PlacementSide::Left);
        $right = $this->join($root, PlacementSide::Right);
        $this->sell($left, 10_000);
        $this->sell($right, 10_000);
        app(MatchingService::class)->runCycle(Carbon::parse('2026-09-20'));

        $earned = $this->balance($root);
        $this->assertGreaterThan(100_000, $earned);

        $this->withdraw($root, 100_000);
        $this->withdraw($root, $earned - 100_000);

        $this->assertSame(0, FraudFlag::query()->count());
    }

    public function test_carried_forward_commission_does_not_count_as_earned()
    {
        $root = $this->root();
        $root->forceFill(['activated_at' => now()->subDays(30)])->save();
        $this->node($root)->forceFill(['deferred_commission' => 500_000])->save();
        app(WalletService::class)->credit($root, 100_000, WalletTransactionType::Adjustment);

        $this->withdraw($root, 100_000);

        $this->assertSame(0, app(FraudScanService::class)->legitimateEarnings($root));
        $this->assertSame([FraudFlag::WITHDRAWAL_EXCEEDS_EARNINGS], FraudFlag::query()->pluck('type')->all());
    }

    public function test_the_scheduled_scan_catches_missed_withdrawals_and_is_idempotent()
    {
        $member = Member::factory()->active()->create(['activated_at' => now()->subHour()]);
        app(WalletService::class)->credit($member, 300_000, WalletTransactionType::Adjustment);
        $this->withdraw($member, 150_000);
        FraudFlag::query()->delete(); // as if the request-time check had never run

        $this->artisan('fraud:scan')->expectsOutputToContain('2 new flag(s)')->assertSuccessful();
        $this->assertSame(2, FraudFlag::query()->count());

        $this->artisan('fraud:scan')->assertSuccessful();
        $this->assertSame(2, FraudFlag::query()->count(), 'No duplicate flags');
    }

    public function test_an_admin_reviews_a_flag_and_the_decision_is_audited()
    {
        $member = Member::factory()->active()->create(['activated_at' => now()->subHour()]);
        app(WalletService::class)->credit($member, 300_000, WalletTransactionType::Adjustment);
        $this->withdraw($member, 150_000);
        $flag = FraudFlag::query()->where('type', FraudFlag::RAPID_WITHDRAWAL)->firstOrFail();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.fraud.review', $flag), ['outcome' => 'dismissed'])
            ->assertSessionHasErrors('note');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.fraud.review', $flag), ['outcome' => 'dismissed', 'note' => 'Called the member — genuine emergency.'])
            ->assertRedirect(route('admin.fraud.index'));

        $flag->refresh();
        $this->assertSame('dismissed', $flag->status);
        $this->assertSame($this->admin->id, $flag->reviewed_by);

        $log = Activity::query()->where('log_name', 'fraud')->latest('id')->firstOrFail();
        $this->assertSame('Fraud flag dismissed', $log->description);
        $this->assertTrue($log->causer?->is($this->admin));
        $this->assertTrue($log->subject?->is($member));

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.fraud.review', $flag), ['outcome' => 'reviewed', 'note' => 'again'])
            ->assertStatus(409);
    }

    public function test_the_fraud_queue_needs_manage_members()
    {
        $finance = Admin::factory()->create()->assignRole('finance');
        $support = Admin::factory()->create()->assignRole('support');

        $this->actingAs($finance, 'admin')->get(route('admin.fraud.index'))->assertForbidden();
        $this->actingAs($support, 'admin')->get(route('admin.fraud.index'))->assertOk();
    }
}
