<?php

namespace App\Notifications;

use App\Enums\WithdrawalStatus;
use App\Models\Withdrawal;
use App\Support\Money;

/**
 * Sent on the request (pending) and on every admin transition. The status
 * is captured when the notification is created: the queued copy re-reads
 * the withdrawal, which may have moved on by then.
 */
class WithdrawalStatusChanged extends MemberNotification
{
    public WithdrawalStatus $status;

    public function __construct(public Withdrawal $withdrawal)
    {
        parent::__construct();

        $this->status = $withdrawal->status;
    }

    public function kind(): string
    {
        return 'withdrawal';
    }

    public function title(): string
    {
        return match ($this->status) {
            WithdrawalStatus::Pending => __('Withdrawal requested'),
            WithdrawalStatus::Approved => __('Withdrawal approved'),
            WithdrawalStatus::Processing => __('Withdrawal processing'),
            WithdrawalStatus::Paid => __('Withdrawal paid'),
            WithdrawalStatus::Rejected => __('Withdrawal rejected'),
        };
    }

    public function message(object $notifiable): string
    {
        $amount = Money::format($this->withdrawal->amount);
        $id = $this->withdrawal->id;

        return match ($this->status) {
            WithdrawalStatus::Pending => __('Your withdrawal #:id of :amount was received. The amount is on hold until it is processed.', ['id' => $id, 'amount' => $amount]),
            WithdrawalStatus::Approved => __('Your withdrawal #:id of :amount was approved and will be paid out soon.', ['id' => $id, 'amount' => $amount]),
            WithdrawalStatus::Processing => __('Your withdrawal #:id of :amount is being paid out now.', ['id' => $id, 'amount' => $amount]),
            WithdrawalStatus::Paid => filled($this->withdrawal->payout_reference)
                ? __('Your withdrawal #:id of :amount was paid (reference :reference).', ['id' => $id, 'amount' => $amount, 'reference' => $this->withdrawal->payout_reference])
                : __('Your withdrawal #:id of :amount was paid.', ['id' => $id, 'amount' => $amount]),
            WithdrawalStatus::Rejected => __('Your withdrawal #:id of :amount was rejected: :reason. The amount is back in your wallet.', ['id' => $id, 'amount' => $amount, 'reason' => $this->withdrawal->rejection_reason]),
        };
    }

    public function path(): string
    {
        return route('withdrawals.index', absolute: false);
    }

    protected function urgent(): bool
    {
        return in_array($this->status, [WithdrawalStatus::Paid, WithdrawalStatus::Rejected], true);
    }

    protected function data(): array
    {
        return ['withdrawal_id' => $this->withdrawal->id, 'status' => $this->status->value, 'amount' => $this->withdrawal->amount];
    }
}
