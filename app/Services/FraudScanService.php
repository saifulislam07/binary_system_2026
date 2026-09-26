<?php

namespace App\Services;

use App\Enums\PayoutStatus;
use App\Enums\WithdrawalStatus;
use App\Models\Bonus;
use App\Models\Commission;
use App\Models\FraudFlag;
use App\Models\Member;
use App\Models\Withdrawal;

/**
 * Rule #12 suspicious-activity checks. Everything here only raises flags for
 * an admin to review — nothing is blocked automatically.
 */
class FraudScanService
{
    /**
     * @return int flags raised by this scan (existing ones are not repeated)
     */
    public function scanRecent(): int
    {
        $raised = 0;

        Withdrawal::query()
            ->where('status', '!=', WithdrawalStatus::Rejected)
            ->where('created_at', '>=', now()->subDays((int) config('business.fraud.scan_days', 30)))
            ->with('member')
            ->chunkById(200, function ($withdrawals) use (&$raised) {
                foreach ($withdrawals as $withdrawal) {
                    $raised += count($this->scanWithdrawal($withdrawal));
                }
            });

        return $raised;
    }

    /**
     * @return list<FraudFlag> flags newly raised for this withdrawal
     */
    public function scanWithdrawal(Withdrawal $withdrawal): array
    {
        $member = $withdrawal->member()->firstOrFail();
        $flags = [];

        // 1. Money out very soon after joining.
        $hours = (int) config('business.fraud.rapid_withdrawal_hours', 72);

        if ($member->activated_at !== null && $withdrawal->created_at !== null
            && $member->activated_at->diffInHours($withdrawal->created_at, true) < $hours) {
            $flags[] = FraudFlag::raise($member, FraudFlag::RAPID_WITHDRAWAL, $withdrawal, [
                'hours_after_activation' => (int) floor($member->activated_at->diffInHours($withdrawal->created_at, true)),
                'threshold_hours' => $hours,
                'amount' => $withdrawal->amount,
            ]);
        }

        // 2. More requested out than the member has legitimately earned.
        $earned = $this->legitimateEarnings($member);
        $withdrawn = (int) Withdrawal::query()
            ->where('member_id', $member->id)
            ->where('status', '!=', WithdrawalStatus::Rejected)
            ->sum('amount');

        if ($withdrawn > $earned) {
            $flags[] = FraudFlag::raise($member, FraudFlag::WITHDRAWAL_EXCEEDS_EARNINGS, $withdrawal, [
                'withdrawn' => $withdrawn,
                'earned' => $earned,
                'excess' => $withdrawn - $earned,
            ]);
        }

        return array_values(array_filter($flags, fn (FraudFlag $flag) => $flag->wasRecentlyCreated));
    }

    /**
     * Lifetime income the member earned through the plan: commissions net of
     * reversals, plus paid bonuses. Deferred (carried-forward, unpaid) and
     * voided amounts never count, and neither do manual wallet adjustments.
     */
    public function legitimateEarnings(Member $member): int
    {
        $commissions = (int) Commission::query()
            ->where('member_id', $member->id)
            ->whereIn('status', [PayoutStatus::Paid, PayoutStatus::Reversed])
            ->sum('amount');

        $bonuses = (int) Bonus::query()
            ->where('member_id', $member->id)
            ->where('status', PayoutStatus::Paid)
            ->sum('amount');

        return $commissions + $bonuses;
    }
}
