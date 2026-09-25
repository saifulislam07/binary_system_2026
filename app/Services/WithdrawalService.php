<?php

namespace App\Services;

use App\Enums\AdminPermission;
use App\Enums\MemberStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalMethodType;
use App\Enums\WithdrawalStatus;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\WithdrawalException;
use App\Models\Admin;
use App\Models\Member;
use App\Models\Setting;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Rule #9. The request places a *pending* wallet debit (the hold) so the
 * money is unavailable immediately. Only admins with manage-withdrawals move
 * the request through pending → approved → processing → paid, or reject it
 * from pending/approved, which voids the hold and restores the balance.
 */
class WithdrawalService
{
    public function __construct(private WalletService $wallets) {}

    public function minimumAmount(): int
    {
        return Setting::int(Setting::MIN_WITHDRAWAL, 100_000);
    }

    /**
     * @param  array<string, string>  $accountDetails  snapshot of where to pay
     */
    public function request(Member $member, int $amount, WithdrawalMethodType $method, array $accountDetails): Withdrawal
    {
        if ($member->status !== MemberStatus::Active) {
            throw new WithdrawalException('Only active members can withdraw.');
        }

        if ($amount < $this->minimumAmount()) {
            throw new WithdrawalException('The minimum withdrawal is '.Money::format($this->minimumAmount()).'.');
        }

        return DB::transaction(function () use ($member, $amount, $method, $accountDetails) {
            $withdrawal = new Withdrawal([
                'member_id' => $member->id,
                'amount' => $amount,
                'method' => $method,
                'account_details' => $accountDetails,
            ]);
            $withdrawal->forceFill(['status' => WithdrawalStatus::Pending])->save();

            try {
                $hold = $this->wallets->debit(
                    $member,
                    $amount,
                    WalletTransactionType::Withdrawal,
                    $withdrawal,
                    "Withdrawal #{$withdrawal->id} ({$method->value})",
                    WalletTransactionStatus::Pending,
                );
            } catch (InsufficientFundsException) {
                throw new WithdrawalException('Insufficient balance for this withdrawal.');
            }

            $withdrawal->forceFill(['wallet_transaction_id' => $hold->id])->save();

            activity('withdrawals')
                ->performedOn($withdrawal)
                ->causedBy($member->user)
                ->withProperties(['amount' => $amount, 'method' => $method->value])
                ->log('Withdrawal requested');

            return $withdrawal;
        }, 3);
    }

    public function approve(Withdrawal $withdrawal, Admin $admin): Withdrawal
    {
        return $this->transition($withdrawal, $admin, WithdrawalStatus::Approved);
    }

    public function startProcessing(Withdrawal $withdrawal, Admin $admin): Withdrawal
    {
        return $this->transition($withdrawal, $admin, WithdrawalStatus::Processing);
    }

    /**
     * Money has left the company: the hold becomes a completed debit.
     */
    public function markPaid(Withdrawal $withdrawal, Admin $admin, ?string $payoutReference = null): Withdrawal
    {
        return $this->transition($withdrawal, $admin, WithdrawalStatus::Paid, function (Withdrawal $w) use ($payoutReference) {
            $this->wallets->complete($this->hold($w));
            $w->forceFill(['processed_at' => now(), 'payout_reference' => $payoutReference]);
        }, ['payout_reference' => $payoutReference]);
    }

    /**
     * Voids the hold so the member gets the full amount back.
     */
    public function reject(Withdrawal $withdrawal, Admin $admin, string $reason): Withdrawal
    {
        if (trim($reason) === '') {
            throw new WithdrawalException('A reason is required to reject a withdrawal.');
        }

        return $this->transition($withdrawal, $admin, WithdrawalStatus::Rejected, function (Withdrawal $w) use ($reason) {
            $this->wallets->void($this->hold($w));
            $w->forceFill(['processed_at' => now(), 'rejection_reason' => $reason]);
        }, ['reason' => $reason]);
    }

    /**
     * @param  (callable(Withdrawal): void)|null  $effects  runs inside the transaction, before the status change is saved
     * @param  array<string, mixed>  $logProperties
     */
    private function transition(Withdrawal $withdrawal, Admin $admin, WithdrawalStatus $to, ?callable $effects = null, array $logProperties = []): Withdrawal
    {
        if (! $admin->hasPermissionTo(AdminPermission::ManageWithdrawals->value, 'admin')) {
            throw new AuthorizationException('This admin may not manage withdrawals.');
        }

        return DB::transaction(function () use ($withdrawal, $admin, $to, $effects, $logProperties) {
            $withdrawal = Withdrawal::query()->lockForUpdate()->findOrFail($withdrawal->id);
            $from = $withdrawal->status;

            if (! $from->canTransitionTo($to)) {
                throw new WithdrawalException("Withdrawal #{$withdrawal->id} cannot go from {$from->value} to {$to->value}.");
            }

            if ($effects !== null) {
                $effects($withdrawal);
            }

            $withdrawal->forceFill(['status' => $to, 'admin_id' => $admin->id])->save();

            activity('withdrawals')
                ->performedOn($withdrawal)
                ->causedBy($admin)
                ->withProperties(['from' => $from->value, 'to' => $to->value, 'amount' => $withdrawal->amount, ...$logProperties])
                ->log("Withdrawal {$to->value}");

            return $withdrawal;
        }, 3);
    }

    private function hold(Withdrawal $withdrawal): WalletTransaction
    {
        return WalletTransaction::query()->findOrFail($withdrawal->wallet_transaction_id);
    }
}
