<?php

namespace Tests\Feature\Wallet;

use App\Enums\WalletTransactionType;
use App\Models\Member;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Hits one wallet from several OS processes at once against the real MySQL
 * test database, so the wallet row lock is genuinely contended. Committed
 * data is required, so this class skips the per-test transaction and forces
 * a fresh migration for whatever runs next.
 */
class WalletConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private const PROCESSES = 4;

    /**
     * @return array<int, string|null>
     */
    protected function connectionsToTransact(): array
    {
        return [];
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;

        parent::tearDown();
    }

    /**
     * @param  list<array{0: string, 1: int, 2: int}>  $jobs  [operation, amount, times] per process
     * @return list<string> every output line from every process
     */
    private function runInParallel(Wallet $wallet, array $jobs): array
    {
        $processes = array_map(function (array $job) use ($wallet) {
            [$operation, $amount, $times] = $job;

            $process = new Process(
                [PHP_BINARY, base_path('tests/Support/wallet-operations.php'), (string) $wallet->id, $operation, (string) $amount, (string) $times],
                base_path(),
                [
                    'APP_ENV' => 'testing',
                    'DB_CONNECTION' => config('database.default'),
                    'DB_DATABASE' => config('database.connections.'.config('database.default').'.database'),
                    'CACHE_STORE' => 'array',
                ],
                timeout: 120,
            );
            $process->start();

            return $process;
        }, $jobs);

        $lines = [];

        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), 'Child failed: '.$process->getErrorOutput().$process->getOutput());
            $lines = [...$lines, ...array_filter(explode(PHP_EOL, trim($process->getOutput())))];
        }

        return $lines;
    }

    public function test_parallel_credits_and_debits_lose_no_updates()
    {
        $wallets = app(WalletService::class);
        $member = Member::factory()->active()->create();
        $wallet = $member->wallet()->create();
        $wallets->credit($wallet, 1_000_000, WalletTransactionType::Adjustment);

        $this->runInParallel($wallet, [
            ['credit', 1_000, 25],
            ['credit', 1_000, 25],
            ['debit', 700, 25],
            ['debit', 700, 25],
        ]);

        $expected = 1_000_000 + 2 * 25 * 1_000 - 2 * 25 * 700;

        $this->assertSame($expected, $wallet->fresh()?->balance);
        $this->assertSame($expected, $wallets->ledgerBalance($wallet));
        $this->assertSame(101, WalletTransaction::query()->where('wallet_id', $wallet->id)->count());
    }

    public function test_racing_debits_never_overdraw_the_wallet()
    {
        $wallets = app(WalletService::class);
        $member = Member::factory()->active()->create();
        $wallet = $member->wallet()->create();
        $wallets->credit($wallet, 10_000, WalletTransactionType::Adjustment);

        // 4 processes × 5 debits of 1,000 = 20,000 requested against 10,000 available.
        $lines = $this->runInParallel($wallet, array_fill(0, self::PROCESSES, ['debit', 1_000, 5]));

        $this->assertCount(20, $lines);
        $this->assertSame(10, count(array_filter($lines, fn ($l) => $l === 'ok')), 'Exactly the affordable debits succeed');
        $this->assertSame(0, $wallet->fresh()?->balance);
        $this->assertSame(0, $wallets->ledgerBalance($wallet));
        $this->assertSame(0, WalletTransaction::query()->where('wallet_id', $wallet->id)->where('balance_after', '<', 0)->count());
    }
}
