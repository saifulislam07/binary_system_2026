<?php

namespace Tests\Feature\Withdrawals;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalMethodType;
use App\Enums\WithdrawalStatus;
use App\Exceptions\WithdrawalException;
use App\Models\Admin;
use App\Models\Member;
use App\Models\Setting;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

#[Group('rule-9')]
class WithdrawalServiceTest extends TestCase
{
    use RefreshDatabase;

    private WithdrawalService $withdrawals;

    private WalletService $wallets;

    private Member $member;

    private Admin $admin;

    private const BKASH = ['provider' => 'bkash', 'mobile_number' => '+8801712345678'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withdrawals = app(WithdrawalService::class);
        $this->wallets = app(WalletService::class);
        $this->member = Member::factory()->active()->create();
        $this->admin = Admin::factory()->superAdmin()->create();

        $this->wallets->credit($this->member, 500_000, WalletTransactionType::ReferralBonus); // ৳5,000
    }

    private function requestWithdrawal(int $amount = 200_000): Withdrawal
    {
        return $this->withdrawals->request($this->member, $amount, WithdrawalMethodType::MobileBanking, self::BKASH);
    }

    private function assertBalance(int $expected): void
    {
        $this->assertSame($expected, $this->wallets->balance($this->member));
        $this->assertSame($expected, $this->wallets->ledgerBalance($this->member), 'cached balance ≠ ledger');
    }

    public function test_request_holds_the_funds_immediately()
    {
        $withdrawal = $this->requestWithdrawal(200_000);

        $this->assertSame(WithdrawalStatus::Pending, $withdrawal->status);
        $this->assertSame(self::BKASH, $withdrawal->account_details);
        $this->assertBalance(300_000);

        $hold = $withdrawal->holdTransaction()->firstOrFail();
        $this->assertSame(WalletTransactionStatus::Pending, $hold->status);
        $this->assertSame(WalletTransactionType::Withdrawal, $hold->type);
        $this->assertSame(200_000, $hold->amount);
        $this->assertTrue($hold->reference->is($withdrawal));
    }

    public function test_full_lifecycle_to_paid_keeps_the_money_out_and_completes_the_hold()
    {
        $withdrawal = $this->requestWithdrawal(200_000);

        $this->withdrawals->approve($withdrawal, $this->admin);
        $this->withdrawals->startProcessing($withdrawal, $this->admin);
        $paid = $this->withdrawals->markPaid($withdrawal, $this->admin, 'BKASH-TRX-9A8B7C');

        $this->assertSame(WithdrawalStatus::Paid, $paid->status);
        $this->assertSame($this->admin->id, $paid->admin_id);
        $this->assertSame('BKASH-TRX-9A8B7C', $paid->payout_reference);
        $this->assertNotNull($paid->processed_at);
        $this->assertSame(WalletTransactionStatus::Completed, $paid->holdTransaction()->firstOrFail()->status);
        $this->assertBalance(300_000);

        $transitions = Activity::query()->where('log_name', 'withdrawals')->where('subject_id', $withdrawal->id)->orderBy('id')->pluck('description')->all();
        $this->assertSame(['Withdrawal requested', 'Withdrawal approved', 'Withdrawal processing', 'Withdrawal paid'], $transitions);
        $this->assertSame($this->admin->id, Activity::query()->where('description', 'Withdrawal paid')->firstOrFail()->causer_id);
    }

    public function test_rejecting_a_pending_withdrawal_restores_the_balance_exactly()
    {
        $withdrawal = $this->requestWithdrawal(200_000);

        $rejected = $this->withdrawals->reject($withdrawal, $this->admin, 'Account name does not match');

        $this->assertSame(WithdrawalStatus::Rejected, $rejected->status);
        $this->assertSame('Account name does not match', $rejected->rejection_reason);
        $this->assertSame(WalletTransactionStatus::Voided, $rejected->holdTransaction()->firstOrFail()->status);
        $this->assertBalance(500_000);
        // Voiding keeps the original row: no extra credit is written.
        $this->assertSame(2, WalletTransaction::query()->count());
    }

    public function test_rejecting_an_approved_withdrawal_restores_the_balance_exactly()
    {
        $withdrawal = $this->requestWithdrawal(200_000);
        $this->withdrawals->approve($withdrawal, $this->admin);

        $this->withdrawals->reject($withdrawal, $this->admin, 'Fraud check');

        $this->assertBalance(500_000);
    }

    public function test_rejection_requires_a_reason()
    {
        $withdrawal = $this->requestWithdrawal();

        $this->expectException(WithdrawalException::class);

        $this->withdrawals->reject($withdrawal, $this->admin, '   ');
    }

    /**
     * @return array<string, array{0: list<string>, 1: string}>
     */
    public static function invalidTransitions(): array
    {
        return [
            'pending → processing' => [[], 'startProcessing'],
            'pending → paid' => [[], 'markPaid'],
            'approved → approved' => [['approve'], 'approve'],
            'approved → paid' => [['approve'], 'markPaid'],
            'processing → rejected' => [['approve', 'startProcessing'], 'reject'],
            'processing → approved' => [['approve', 'startProcessing'], 'approve'],
            'paid → approved' => [['approve', 'startProcessing', 'markPaid'], 'approve'],
            'paid → rejected' => [['approve', 'startProcessing', 'markPaid'], 'reject'],
            'rejected → approved' => [['reject'], 'approve'],
            'rejected → rejected' => [['reject'], 'reject'],
        ];
    }

    /**
     * @param  list<string>  $setup
     */
    #[DataProvider('invalidTransitions')]
    public function test_invalid_transitions_are_blocked(array $setup, string $action)
    {
        $withdrawal = $this->requestWithdrawal(200_000);
        $call = fn (string $method) => $method === 'reject'
            ? $this->withdrawals->reject($withdrawal, $this->admin, 'reason')
            : $this->withdrawals->{$method}($withdrawal, $this->admin);

        foreach ($setup as $step) {
            $call($step);
        }

        $statusBefore = $withdrawal->fresh()?->status;
        $balanceBefore = $this->wallets->balance($this->member);

        try {
            $call($action);
            $this->fail("Expected {$action} to be refused");
        } catch (WithdrawalException) {
            // expected
        }

        $this->assertSame($statusBefore, $withdrawal->fresh()?->status);
        $this->assertBalance($balanceBefore);
    }

    public function test_status_machine_matches_rule_9()
    {
        $this->assertSame([WithdrawalStatus::Approved, WithdrawalStatus::Rejected], WithdrawalStatus::Pending->allowedTransitions());
        $this->assertSame([WithdrawalStatus::Processing, WithdrawalStatus::Rejected], WithdrawalStatus::Approved->allowedTransitions());
        $this->assertSame([WithdrawalStatus::Paid], WithdrawalStatus::Processing->allowedTransitions());
        $this->assertSame([], WithdrawalStatus::Paid->allowedTransitions());
        $this->assertSame([], WithdrawalStatus::Rejected->allowedTransitions());
    }

    public function test_admins_without_manage_withdrawals_cannot_transition()
    {
        $withdrawal = $this->requestWithdrawal();
        $limited = Admin::factory()->create();
        $limited->givePermissionTo('manage-members');

        $this->expectException(AuthorizationException::class);

        try {
            $this->withdrawals->approve($withdrawal, $limited);
        } finally {
            $this->assertSame(WithdrawalStatus::Pending, $withdrawal->fresh()?->status);
        }
    }

    public function test_cannot_withdraw_below_the_minimum_or_above_the_balance()
    {
        Setting::query()->where('key', Setting::MIN_WITHDRAWAL)->update(['value' => '100000']);

        try {
            $this->requestWithdrawal(99_999);
            $this->fail('Below minimum accepted');
        } catch (WithdrawalException) {
        }

        try {
            $this->requestWithdrawal(500_001);
            $this->fail('Overdraft accepted');
        } catch (WithdrawalException) {
        }

        $this->assertSame(0, Withdrawal::query()->count(), 'A failed request leaves no withdrawal behind');
        $this->assertBalance(500_000);
    }

    public function test_held_funds_cannot_be_withdrawn_twice()
    {
        $this->requestWithdrawal(300_000);

        $this->expectException(WithdrawalException::class);

        $this->requestWithdrawal(300_000); // only ৳2,000 left
    }

    public function test_pending_members_cannot_withdraw()
    {
        $pending = Member::factory()->create();

        $this->expectException(WithdrawalException::class);

        $this->withdrawals->request($pending, 100_000, WithdrawalMethodType::MobileBanking, self::BKASH);
    }
}
