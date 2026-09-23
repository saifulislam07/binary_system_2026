<?php

namespace App\Services;

use App\Enums\CommissionType;
use App\Enums\PayoutStatus;
use App\Enums\PlacementSide;
use App\Enums\SaleStatus;
use App\Enums\WalletTransactionType;
use App\Models\BinaryNode;
use App\Models\Commission;
use App\Models\Member;
use App\Models\Sale;
use App\Models\TeamVolume;
use App\Models\VolumeConsumption;
use App\Models\VolumeLot;
use Illuminate\Support\Facades\DB;

/**
 * Rule #10: undo everything a refunded sale generated, with explicit
 * reversal entries only — originals are never edited or deleted.
 *
 * For each upline lot of the sale:
 *  - unmatched remainder → removed from the node's volume;
 *  - each matched consumption → claw back that share of the cycle's paid
 *    commission (and of any carried-forward overflow), and undo the pairing
 *    on the partner side: the partner volume is restored if carry-forward
 *    was on in that cycle, or dissolved if it would have been flushed anyway;
 *  - lifetime volume is reduced by the lot's full BV.
 * Referral bonuses from the sale are reversed in full.
 */
class CommissionReversalService
{
    public function __construct(
        private WalletService $wallets,
        private TeamVolumeService $volumes,
    ) {}

    public function reverseSale(Sale $sale): void
    {
        DB::transaction(function () use ($sale) {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);

            if ($sale->status !== SaleStatus::Refunded || $sale->reversed_at !== null) {
                return;
            }

            // Lock order matches matching/accrual: nodes (top-down) before any wallet.
            $lotIds = VolumeLot::query()->where('sale_id', $sale->id)->pluck('id');
            $memberIds = VolumeLot::query()->whereKey($lotIds)->distinct()->pluck('member_id')->map(fn ($id) => (int) $id)->all();
            $nodes = $this->volumes->lockNodes($memberIds);

            $lots = VolumeLot::query()->whereKey($lotIds)->orderBy('id')->lockForUpdate()->get();

            $this->reverseReferralBonuses($sale);

            foreach ($lots as $lot) {
                $this->reverseLot($lot, $nodes[$lot->member_id], $sale);
            }

            foreach ($nodes as $node) {
                $node->save();
            }

            $sale->forceFill(['reversed_at' => now()])->save();

            activity('commission')
                ->performedOn($sale)
                ->withProperties(['lots' => $lots->count(), 'bv' => $sale->bv_value])
                ->log('Sale volume and commissions reversed');
        }, 3);
    }

    private function reverseReferralBonuses(Sale $sale): void
    {
        $referrals = Commission::query()
            ->where('source_sale_id', $sale->id)
            ->where('type', CommissionType::Referral)
            ->where('status', PayoutStatus::Paid)
            ->whereNull('reverses_commission_id')
            ->get();

        foreach ($referrals as $referral) {
            $alreadyReversed = Commission::query()->where('reverses_commission_id', $referral->id)->exists();

            if (! $alreadyReversed) {
                $this->clawBack(Member::query()->findOrFail($referral->member_id), $referral->amount, $sale, $referral, null, 'Referral bonus reversed (sale refunded)');
            }
        }
    }

    private function reverseLot(VolumeLot $lot, BinaryNode $node, Sale $sale): void
    {
        $side = $lot->side;
        $volumeColumn = $side->volumeColumn();
        $lifetimeColumn = $side->value.'_lifetime_volume';

        // 1. Unmatched remainder (includes volume restored to it by earlier refunds).
        if ($lot->remaining > 0) {
            VolumeConsumption::query()->create([
                'volume_lot_id' => $lot->id,
                'kind' => VolumeConsumption::REFUNDED,
                'bv' => $lot->remaining,
            ]);
            $node->{$volumeColumn} -= $lot->remaining;
            $lot->forceFill(['remaining' => 0])->save();
        }

        // 2. Lifetime volume never included the refunded sale.
        $node->{$lifetimeColumn} -= $lot->bv;

        // 3. Matched pairings.
        $matches = VolumeConsumption::query()
            ->with('teamVolume')
            ->where('volume_lot_id', $lot->id)
            ->where('kind', VolumeConsumption::MATCHED)
            ->orderBy('id')
            ->get();

        foreach ($matches as $match) {
            $effective = $match->bv - $this->undone($match);

            if ($effective <= 0 || $match->teamVolume === null) {
                continue; // pairing already undone when the partner sale was refunded
            }

            $this->clawBackCycleCommission($node, $match->teamVolume, $effective, $sale);
            $this->undoPartnerPairing($node, $side->opposite(), $match->teamVolume, $effective);
        }
    }

    /**
     * Take back the share of that cycle's commission earned by `$bv` of matched volume.
     */
    private function clawBackCycleCommission(BinaryNode $node, TeamVolume $teamVolume, int $bv, Sale $sale): void
    {
        if ($teamVolume->matched_volume <= 0) {
            return;
        }

        $member = Member::query()->findOrFail($node->member_id);

        // Share of what was paid out for this cycle's own matching.
        $paidShare = intdiv($teamVolume->paid_commission * $bv, $teamVolume->matched_volume);

        if ($paidShare > 0) {
            $original = Commission::query()
                ->where('team_volume_id', $teamVolume->id)
                ->where('status', PayoutStatus::Paid)
                ->where('description', MatchingService::OWN_COMMISSION)
                ->first();

            $this->clawBack($member, $paidShare, $sale, $original, $teamVolume, 'Binary commission reversed (sale refunded)');
        }

        // Share of the above-cap overflow that was carried forward: take it
        // from the deferred balance, or from the wallet if it was already released.
        if ($teamVolume->overflow_action === 'carry_forward' && $teamVolume->overflow_commission > 0) {
            $overflowShare = intdiv($teamVolume->overflow_commission * $bv, $teamVolume->matched_volume);
            $fromDeferred = min($overflowShare, $node->deferred_commission);
            $node->deferred_commission -= $fromDeferred;

            if ($overflowShare - $fromDeferred > 0) {
                $this->clawBack($member, $overflowShare - $fromDeferred, $sale, null, $teamVolume, 'Released carried-forward commission reversed (sale refunded)');
            }
        }
    }

    /**
     * The volume on the other side that was paired with the refunded volume
     * is freed: given back (carry-forward on) or marked dissolved (it would
     * have been flushed in that cycle anyway). Latest-consumed first.
     */
    private function undoPartnerPairing(BinaryNode $node, PlacementSide $partnerSide, TeamVolume $teamVolume, int $bv): void
    {
        $partners = VolumeConsumption::query()
            ->select('volume_consumptions.*')
            ->join('volume_lots', 'volume_lots.id', '=', 'volume_consumptions.volume_lot_id')
            ->where('volume_consumptions.team_volume_id', $teamVolume->id)
            ->where('volume_consumptions.kind', VolumeConsumption::MATCHED)
            ->where('volume_lots.member_id', $node->member_id)
            ->where('volume_lots.side', $partnerSide)
            ->orderByDesc('volume_consumptions.id')
            ->get();

        $need = $bv;

        foreach ($partners as $partner) {
            if ($need === 0) {
                break;
            }

            $take = min($partner->bv - $this->undone($partner), $need);

            if ($take <= 0) {
                continue;
            }

            if ($teamVolume->carry_forward_enabled) {
                VolumeLot::query()->whereKey($partner->volume_lot_id)->lockForUpdate()->increment('remaining', $take);
                $node->{$partnerSide->volumeColumn()} += $take;
            }

            VolumeConsumption::query()->create([
                'volume_lot_id' => $partner->volume_lot_id,
                'team_volume_id' => $teamVolume->id,
                'kind' => $teamVolume->carry_forward_enabled ? VolumeConsumption::RESTORED : VolumeConsumption::DISSOLVED,
                'bv' => $take,
                'restores_consumption_id' => $partner->id,
            ]);

            $need -= $take;
        }
    }

    /**
     * How much of a matched consumption has already been undone by refunds.
     */
    private function undone(VolumeConsumption $match): int
    {
        return (int) VolumeConsumption::query()
            ->where('restores_consumption_id', $match->id)
            ->whereIn('kind', [VolumeConsumption::RESTORED, VolumeConsumption::DISSOLVED])
            ->sum('bv');
    }

    /**
     * A negative commission row (status reversed) plus the matching wallet debit.
     * The wallet may go negative: the member may already have withdrawn it.
     */
    private function clawBack(Member $member, int $amount, Sale $sale, ?Commission $original, ?TeamVolume $teamVolume, string $description): void
    {
        $reversal = Commission::query()->create([
            'member_id' => $member->id,
            'type' => $original !== null ? $original->type : CommissionType::Binary,
            'source_sale_id' => $sale->id,
            'commission_cycle_id' => $teamVolume?->commission_cycle_id,
            'team_volume_id' => $teamVolume?->id,
            'reverses_commission_id' => $original?->id,
            'amount' => -$amount,
            'cycle_date' => today(),
            'status' => PayoutStatus::Reversed,
            'description' => $description,
        ]);

        $this->wallets->debit($member, $amount, WalletTransactionType::Reversal, $reversal, $description, allowNegative: true);
    }
}
