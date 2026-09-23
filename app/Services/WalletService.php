<?php

namespace App\Services;

use App\Enums\TransactionDirection;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientFundsException;
use App\Models\Member;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The ONLY code that may change a wallet. Every call appends one ledger row
 * and updates the cached `wallets.balance` in the same transaction, with the
 * wallet row locked. Balance = SUM(credits) − SUM(debits) of non-voided rows.
 */
class WalletService
{
    public function credit(
        Member|Wallet $owner,
        int $amount,
        WalletTransactionType $type,
        ?Model $reference = null,
        ?string $description = null,
        WalletTransactionStatus $status = WalletTransactionStatus::Completed,
    ): WalletTransaction {
        return $this->post($owner, TransactionDirection::Credit, $amount, $type, $reference, $description, $status);
    }

    /**
     * @param  bool  $allowNegative  only for clawbacks (refund reversals): the
     *                               member may already have withdrawn the money, so the
     *                               wallet goes negative and future earnings repay it.
     */
    public function debit(
        Member|Wallet $owner,
        int $amount,
        WalletTransactionType $type,
        ?Model $reference = null,
        ?string $description = null,
        WalletTransactionStatus $status = WalletTransactionStatus::Completed,
        bool $allowNegative = false,
    ): WalletTransaction {
        return $this->post($owner, TransactionDirection::Debit, $amount, $type, $reference, $description, $status, $allowNegative);
    }

    /**
     * Cached balance (fast path for display).
     */
    public function balance(Member|Wallet $owner): int
    {
        return $this->walletFor($owner)->balance;
    }

    /**
     * The wallet's ledger, newest first.
     *
     * @return LengthAwarePaginator<int, WalletTransaction>
     */
    public function history(
        Member|Wallet $owner,
        ?WalletTransactionType $type = null,
        ?CarbonInterface $from = null,
        ?CarbonInterface $to = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        return WalletTransaction::query()
            ->with('reference')
            ->where('wallet_id', $this->walletFor($owner)->id)
            ->when($type, fn ($q) => $q->where('type', $type))
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to->copy()->endOfDay()))
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Balance recomputed from the ledger — the source of truth.
     */
    public function ledgerBalance(Member|Wallet $owner): int
    {
        $walletId = $this->walletFor($owner)->id;

        return (int) WalletTransaction::query()
            ->where('wallet_id', $walletId)
            ->where('status', '!=', WalletTransactionStatus::Voided)
            ->sum(DB::raw("CASE WHEN direction = 'credit' THEN amount ELSE -amount END"));
    }

    private function post(
        Member|Wallet $owner,
        TransactionDirection $direction,
        int $amount,
        WalletTransactionType $type,
        ?Model $reference,
        ?string $description,
        WalletTransactionStatus $status,
        bool $allowNegative = false,
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException("Wallet amounts must be positive; got [{$amount}].");
        }

        if ($status === WalletTransactionStatus::Voided) {
            throw new InvalidArgumentException('Cannot post a transaction that is already voided.');
        }

        return DB::transaction(function () use ($owner, $direction, $amount, $type, $reference, $description, $status, $allowNegative) {
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($this->walletFor($owner)->id);

            $newBalance = $direction === TransactionDirection::Credit
                ? $wallet->balance + $amount
                : $wallet->balance - $amount;

            if ($newBalance < 0 && ! $allowNegative) {
                throw new InsufficientFundsException("Wallet [{$wallet->id}] has insufficient balance.");
            }

            $transaction = $wallet->transactions()->create([
                'type' => $type,
                'direction' => $direction,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'description' => $description,
                'status' => $status,
            ]);

            $wallet->forceFill(['balance' => $newBalance])->save();

            activity('wallet')
                ->performedOn($wallet)
                ->withProperties([
                    'transaction_id' => $transaction->id,
                    'direction' => $direction->value,
                    'type' => $type->value,
                    'amount' => $amount,
                    'balance_after' => $newBalance,
                ])
                ->log("Wallet {$direction->value}");

            return $transaction;
        }, 3);
    }

    private function walletFor(Member|Wallet $owner): Wallet
    {
        if ($owner instanceof Wallet) {
            return $owner;
        }

        return $owner->wallet()->firstOrCreate();
    }
}
