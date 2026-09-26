<?php

namespace App\Services;

use App\Enums\TransactionDirection;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\InsufficientFundsException;
use App\Models\Member;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Notifications\IncomeReceived;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
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
     * pending → completed (e.g. a withdrawal hold that was paid out). The
     * balance already reflects a pending row, so it does not change.
     */
    public function complete(WalletTransaction $transaction): WalletTransaction
    {
        return $this->settlePending($transaction, WalletTransactionStatus::Completed);
    }

    /**
     * pending → voided (e.g. a rejected withdrawal). Voided rows are excluded
     * from the balance, so the held amount comes back exactly. The original
     * row is kept for the audit trail; only its status changes.
     */
    public function void(WalletTransaction $transaction): WalletTransaction
    {
        return $this->settlePending($transaction, WalletTransactionStatus::Voided);
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

            if ($direction === TransactionDirection::Credit && $status === WalletTransactionStatus::Completed
                && in_array($type, IncomeReceived::TYPES, true)) {
                $wallet->member()->firstOrFail()->user()->firstOrFail()->notify(new IncomeReceived($transaction));
            }

            return $transaction;
        }, 3);
    }

    private function settlePending(WalletTransaction $transaction, WalletTransactionStatus $to): WalletTransaction
    {
        return DB::transaction(function () use ($transaction, $to) {
            // Same lock order as post(): wallet first, then its row.
            $wallet = Wallet::query()->lockForUpdate()->findOrFail($transaction->wallet_id);
            $transaction = WalletTransaction::query()->lockForUpdate()->findOrFail($transaction->id);

            if ($transaction->status !== WalletTransactionStatus::Pending) {
                throw new InvalidArgumentException("Only pending transactions can be settled; [{$transaction->id}] is {$transaction->status->value}.");
            }

            $transaction->forceFill(['status' => $to])->save();

            if ($to === WalletTransactionStatus::Voided) {
                $wallet->forceFill(['balance' => $wallet->balance - $transaction->signedAmount()])->save();
            }

            activity('wallet')
                ->performedOn($wallet)
                ->withProperties([
                    'transaction_id' => $transaction->id,
                    'status' => $to->value,
                    'amount' => $transaction->amount,
                    'balance' => $wallet->balance,
                ])
                ->log("Wallet transaction {$to->value}");

            return $transaction;
        }, 3);
    }

    private function walletFor(Member|Wallet $owner): Wallet
    {
        if ($owner instanceof Wallet) {
            return $owner;
        }

        $wallet = $owner->wallet()->firstOrCreate();

        // A freshly inserted model doesn't carry DB defaults (balance = 0) until reloaded.
        return $wallet->wasRecentlyCreated ? $wallet->refresh() : $wallet;
    }
}
