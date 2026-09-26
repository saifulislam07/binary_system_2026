<?php

namespace Tests\Feature\Wallet;

use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientFundsException;
use App\Models\Member;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

#[Group('rule-8')]
class WalletLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_balance_always_equals_the_ledger_after_any_sequence_of_operations()
    {
        $wallets = app(WalletService::class);
        $member = Member::factory()->active()->create();
        mt_srand(20260923); // deterministic "random" sequence

        $expected = 0;

        for ($i = 0; $i < 200; $i++) {
            $amount = mt_rand(1, 50_000);
            $isCredit = mt_rand(0, 2) > 0 || $expected === 0;

            try {
                $isCredit
                    ? $wallets->credit($member, $amount, WalletTransactionType::cases()[mt_rand(0, 5)])
                    : $wallets->debit($member, $amount, WalletTransactionType::Withdrawal);
                $expected += $isCredit ? $amount : -$amount;
            } catch (InsufficientFundsException) {
                $this->assertGreaterThan($expected, $amount, 'Only overdrafts may be refused');
            }

            $this->assertSame($expected, $wallets->balance($member), "after operation {$i}");
            $this->assertSame($expected, $wallets->ledgerBalance($member), "ledger after operation {$i}");
        }

        // Every row's balance_after is the running total.
        $running = 0;
        foreach (WalletTransaction::query()->orderBy('id')->get() as $row) {
            $running += $row->signedAmount();
            $this->assertSame($running, $row->balance_after);
        }
    }

    public function test_history_filters_by_type_and_date_and_is_newest_first()
    {
        $wallets = app(WalletService::class);
        $member = Member::factory()->active()->create();

        Carbon::setTestNow('2026-09-01 10:00');
        $wallets->credit($member, 1_000, WalletTransactionType::ReferralBonus);
        Carbon::setTestNow('2026-09-10 10:00');
        $wallets->credit($member, 2_000, WalletTransactionType::BinaryCommission);
        Carbon::setTestNow('2026-09-20 10:00');
        $wallets->credit($member, 3_000, WalletTransactionType::ReferralBonus);
        Carbon::setTestNow();

        $this->assertSame([3_000, 2_000, 1_000], $wallets->history($member)->pluck('amount')->all());
        $this->assertSame([3_000, 1_000], $wallets->history($member, WalletTransactionType::ReferralBonus)->pluck('amount')->all());
        $this->assertSame([2_000], $wallets->history($member, null, Carbon::parse('2026-09-05'), Carbon::parse('2026-09-15'))->pluck('amount')->all());
        $this->assertSame([1_000], $wallets->history($member, WalletTransactionType::ReferralBonus, null, Carbon::parse('2026-09-01'))->pluck('amount')->all());
    }

    /**
     * Architecture rule: WalletService is the only code allowed to write
     * ledger rows or the cached balance. Fails the build if anything else does.
     */
    public function test_only_wallet_service_writes_to_wallets()
    {
        $forbidden = [
            '/WalletTransaction::(query\(\)->)?(create|insert|forceCreate|updateOrCreate|firstOrCreate)\b/',
            '/->transactions\(\)->(create|insert|forceCreate|createMany|save)\b/',
            // 'balance' inside a write call (not display props or the model's cast)
            '/(forceFill|update|fill|create|insert|updateOrCreate)\(\s*\[[^\]]*[\'"]balance[\'"]\s*=>/s',
            '/->balance\s*(\+|-)?=[^=]/',
            '/Wallet::query\(\)(->[a-zA-Z]+\([^)]*\))*->(update|increment|decrement|incrementEach)\(/',
            '/->wallet\(\)->(update|increment|decrement)\(/',
        ];

        $offenders = [];

        foreach ((new Finder)->files()->in(app_path())->name('*.php') as $file) {
            if (str_ends_with($file->getRealPath(), 'Services'.DIRECTORY_SEPARATOR.'WalletService.php')) {
                continue;
            }

            foreach ($forbidden as $pattern) {
                if (preg_match($pattern, $file->getContents()) === 1) {
                    $offenders[] = $file->getRelativePathname().' matches '.$pattern;
                }
            }
        }

        $this->assertSame([], $offenders, "Wallet writes must go through WalletService:\n".implode("\n", $offenders));
    }
}
