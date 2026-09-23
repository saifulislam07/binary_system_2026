<?php

/*
| Child-process helper for WalletConcurrencyTest.
| Usage: php tests/Support/wallet-operations.php <wallet-id> <credit|debit> <amount> <times>
| Prints one line per operation: "ok" or "insufficient".
*/

use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientFundsException;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $walletId, $operation, $amount, $times] = $argv;

$wallets = $app->make(WalletService::class);
$wallet = Wallet::query()->findOrFail((int) $walletId);

for ($i = 0; $i < (int) $times; $i++) {
    try {
        $operation === 'credit'
            ? $wallets->credit($wallet, (int) $amount, WalletTransactionType::Adjustment)
            : $wallets->debit($wallet, (int) $amount, WalletTransactionType::Withdrawal);
        echo 'ok', PHP_EOL;
    } catch (InsufficientFundsException) {
        echo 'insufficient', PHP_EOL;
    }
}
