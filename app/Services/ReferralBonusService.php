<?php

namespace App\Services;

use App\Enums\CommissionType;
use App\Enums\MemberStatus;
use App\Enums\PayoutStatus;
use App\Enums\WalletTransactionType;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\Member;
use App\Models\Sale;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Rule #7: referral bonus = qualifying sale amount × referral rate, paid to
 * the SPONSOR (not the placement parent).
 */
class ReferralBonusService
{
    public function __construct(private WalletService $wallets) {}

    public function payOnSale(Sale $sale): ?Commission
    {
        return DB::transaction(function () use ($sale) {
            $sale = Sale::query()->with(['package', 'member'])->lockForUpdate()->findOrFail($sale->id);

            if (! $sale->package->is_qualifying || $sale->member->sponsor_id === null) {
                return null;
            }

            $existing = Commission::query()
                ->where('source_sale_id', $sale->id)
                ->where('type', CommissionType::Referral)
                ->whereNull('reverses_commission_id')
                ->first();

            if ($existing !== null) {
                return $existing; // idempotent
            }

            $sponsor = Member::query()->findOrFail($sale->member->sponsor_id);
            $amount = Money::percentOf($sale->amount, CommissionRule::int(CommissionRule::REFERRAL_RATE_BPS));

            if ($amount <= 0) {
                return null;
            }

            // Suspended sponsors don't earn; keep a voided row so the decision is auditable.
            $payable = $sponsor->status === MemberStatus::Active;

            $commission = Commission::query()->create([
                'member_id' => $sponsor->id,
                'type' => CommissionType::Referral,
                'source_sale_id' => $sale->id,
                'amount' => $amount,
                'cycle_date' => today(),
                'status' => $payable ? PayoutStatus::Paid : PayoutStatus::Voided,
                'description' => "Referral bonus for {$sale->member->member_code}".($payable ? '' : ' (sponsor not active)'),
            ]);

            if ($payable) {
                $this->wallets->credit($sponsor, $amount, WalletTransactionType::ReferralBonus, $commission, $commission->description);
            }

            return $commission;
        }, 3);
    }
}
