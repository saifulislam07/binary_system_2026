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
            WithdrawalStatus::Pending => 'Withdrawal requested · উত্তোলনের অনুরোধ গৃহীত',
            WithdrawalStatus::Approved => 'Withdrawal approved · উত্তোলন অনুমোদিত',
            WithdrawalStatus::Processing => 'Withdrawal processing · উত্তোলন প্রক্রিয়াধীন',
            WithdrawalStatus::Paid => 'Withdrawal paid · উত্তোলন পরিশোধিত',
            WithdrawalStatus::Rejected => 'Withdrawal rejected · উত্তোলন বাতিল',
        };
    }

    public function message(object $notifiable): string
    {
        $amount = Money::format($this->withdrawal->amount);
        $id = $this->withdrawal->id;

        return match ($this->status) {
            WithdrawalStatus::Pending => "Your withdrawal #{$id} of {$amount} was received. The amount is on hold until it is processed.",
            WithdrawalStatus::Approved => "Your withdrawal #{$id} of {$amount} was approved and will be paid out soon.",
            WithdrawalStatus::Processing => "Your withdrawal #{$id} of {$amount} is being paid out now.",
            WithdrawalStatus::Paid => "Your withdrawal #{$id} of {$amount} was paid"
                .(filled($this->withdrawal->payout_reference) ? " (reference {$this->withdrawal->payout_reference})" : '').'.',
            WithdrawalStatus::Rejected => "Your withdrawal #{$id} of {$amount} was rejected: {$this->withdrawal->rejection_reason}. The amount is back in your wallet.",
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
