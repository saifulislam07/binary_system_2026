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
            WalletTransactionType::ReferralBonus => __('Referral bonus received'),
            WalletTransactionType::BinaryCommission => __('Binary commission received'),
            WalletTransactionType::RankBonus => __('Rank bonus received'),
            default => __('Bonus received'),
        };
    }

    public function message(object $notifiable): string
    {
        $amount = Money::format($this->transaction->amount);

        return filled($this->transaction->description)
            ? __(':amount was added to your wallet — :description.', ['amount' => $amount, 'description' => $this->transaction->description])
            : __(':amount was added to your wallet.', ['amount' => $amount]);
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
