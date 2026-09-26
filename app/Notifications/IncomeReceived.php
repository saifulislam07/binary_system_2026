<?php

namespace App\Notifications;

use App\Enums\WalletTransactionType;
use App\Models\WalletTransaction;
use App\Support\Money;

/**
 * Commission or bonus credited to the member's wallet.
 */
class IncomeReceived extends MemberNotification
{
    /**
     * Wallet credit types that count as earned income.
     */
    public const TYPES = [
        WalletTransactionType::ReferralBonus,
        WalletTransactionType::BinaryCommission,
        WalletTransactionType::RankBonus,
        WalletTransactionType::SalesBonus,
        WalletTransactionType::LeadershipBonus,
        WalletTransactionType::PerformanceBonus,
    ];

    public function __construct(public WalletTransaction $transaction)
    {
        parent::__construct();
    }

    public function kind(): string
    {
        return 'income';
    }

    public function title(): string
    {
        return match ($this->transaction->type) {
            WalletTransactionType::ReferralBonus => 'Referral bonus received · রেফারেল বোনাস',
            WalletTransactionType::BinaryCommission => 'Binary commission received · বাইনারি কমিশন',
            WalletTransactionType::RankBonus => 'Rank bonus received · র‍্যাঙ্ক বোনাস',
            default => 'Bonus received · বোনাস',
        };
    }

    public function message(object $notifiable): string
    {
        return Money::format($this->transaction->amount).' was added to your wallet'
            .(filled($this->transaction->description) ? ' — '.$this->transaction->description : '')
            .'.';
    }

    public function path(): string
    {
        return route('income.index', absolute: false);
    }

    protected function data(): array
    {
        return ['amount' => $this->transaction->amount, 'income_type' => $this->transaction->type->value];
    }
}
