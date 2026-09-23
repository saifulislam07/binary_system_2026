<?php

namespace App\Services;

use App\Enums\CommissionCycleStatus;
use App\Enums\CommissionType;
use App\Enums\PayoutStatus;
use App\Enums\TransactionDirection;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Commission;
use App\Models\CommissionCycle;
use App\Models\Member;
use App\Models\WalletTransaction;
use Carbon\CarbonInterface;

/**
 * Numbers for the member's wallet summary widget. All amounts are poysha
 * and net of reversals.
 */
class IncomeSummaryService
{
    public function __construct(private WalletService $wallets) {}

    /**
     * @return array{available: int, period: string, period_start: string, referral: int, binary: int, binary_cycle: string|null, lifetime: int}
     */
    public function for(Member $member, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        [$periodLabel, $from, $to] = $this->currentPeriod($now);

        $lastCycle = CommissionCycle::query()
            ->where('status', CommissionCycleStatus::Closed)
            ->orderByDesc('cycle_date')
            ->first();

        return [
            'available' => $this->wallets->balance($member),
            'period' => $periodLabel,
            'period_start' => $from->toDateString(),
            // Referral bonuses are paid as sales happen, so "this cycle" = the current period.
            'referral' => $this->netCommission($member, CommissionType::Referral, $from, $to),
            // Binary commission is paid when a cycle closes, so show the last closed cycle.
            'binary' => $lastCycle === null ? 0 : $this->netCommission($member, CommissionType::Binary, $lastCycle->cycle_date, $lastCycle->cycle_date),
            'binary_cycle' => $lastCycle?->cycle_date->toDateString(),
            'lifetime' => $this->lifetimeIncome($member),
        ];
    }

    private function netCommission(Member $member, CommissionType $type, CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) Commission::query()
            ->where('member_id', $member->id)
            ->where('type', $type)
            ->whereIn('status', [PayoutStatus::Paid, PayoutStatus::Reversed])
            ->whereBetween('cycle_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');
    }

    /**
     * Everything ever credited as income, minus clawbacks.
     */
    private function lifetimeIncome(Member $member): int
    {
        $base = WalletTransaction::query()
            ->whereHas('wallet', fn ($q) => $q->where('member_id', $member->id))
            ->where('status', '!=', WalletTransactionStatus::Voided);

        $earned = (int) (clone $base)
            ->where('direction', TransactionDirection::Credit)
            ->whereIn('type', WalletTransactionType::income())
            ->sum('amount');

        $clawedBack = (int) (clone $base)
            ->where('direction', TransactionDirection::Debit)
            ->where('type', WalletTransactionType::Reversal)
            ->sum('amount');

        return $earned - $clawedBack;
    }

    /**
     * @return array{0: string, 1: CarbonInterface, 2: CarbonInterface}
     */
    private function currentPeriod(CarbonInterface $now): array
    {
        if (config('business.commission_cycle') === 'weekly') {
            $weekStart = (int) config('business.week_starts_on', CarbonInterface::SATURDAY);

            return ['This week', $now->copy()->startOfWeek($weekStart), $now->copy()->endOfWeek(($weekStart + 6) % 7)];
        }

        return ['Today', $now->copy()->startOfDay(), $now->copy()->endOfDay()];
    }
}
