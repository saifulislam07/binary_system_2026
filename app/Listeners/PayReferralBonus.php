<?php

namespace App\Listeners;

use App\Events\SaleCompleted;
use App\Services\ReferralBonusService;

/**
 * Runs synchronously inside the payment transaction (see SaleCompleted).
 */
class PayReferralBonus
{
    public function __construct(private ReferralBonusService $referrals) {}

    public function handle(SaleCompleted $event): void
    {
        $this->referrals->payOnSale($event->sale);
    }
}
