<?php

namespace Tests\Feature\Commission;

use App\Enums\TransactionDirection;
use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientFundsException;
use App\Models\Member;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    private WalletService $wallets;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wallets = app(WalletService::class);
        $this->member = Member::factory()->active()->create();
    }

    public function test_credit_and_debit_append_ledger_rows_and_update_the_cached_balance()
    {
        $credit = $this->wallets->credit($this->member, 50_000, WalletTransactionType::ReferralBonus, description: 'bonus');
        $debit = $this->wallets->debit($this->member, 20_000, WalletTransactionType::Withdrawal);

        $this->assertSame(TransactionDirection::Credit, $credit->direction);
        $this->assertSame(50_000, $credit->balance_after);
        $this->assertSame(30_000, $debit->balance_after);
        $this->assertSame(30_000, $this->wallets->balance($this->member));
        $this->assertSame(30_000, $this->wallets->ledgerBalance($this->member));
        $this->assertSame(2, Activity::query()->where('log_name', 'wallet')->count());
    }

    public function test_debit_cannot_overdraw_unless_explicitly_allowed()
    {
        $this->wallets->credit($this->member, 10_000, WalletTransactionType::Adjustment);

        try {
            $this->wallets->debit($this->member, 10_001, WalletTransactionType::Withdrawal);
            $this->fail('Expected InsufficientFundsException');
        } catch (InsufficientFundsException) {
            // expected
        }

        $this->assertSame(10_000, $this->wallets->balance($this->member));

        $this->wallets->debit($this->member, 15_000, WalletTransactionType::Reversal, allowNegative: true);
        $this->assertSame(-5_000, $this->wallets->balance($this->member));
        $this->assertSame(-5_000, $this->wallets->ledgerBalance($this->member));
    }

    public function test_amounts_must_be_positive()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->wallets->credit($this->member, 0, WalletTransactionType::Adjustment);
    }
}
