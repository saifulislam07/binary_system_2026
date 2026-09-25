<?php

namespace App\Services;

use App\DTOs\MemberStats;
use App\Enums\BonusType;
use App\Enums\MemberStatus;
use App\Enums\PayoutStatus;
use App\Enums\WalletTransactionType;
use App\Exceptions\MemberAdminException;
use App\Models\Admin;
use App\Models\Bonus;
use App\Models\BonusRule;
use App\Models\Member;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Leadership, sales and performance bonuses. All pay out the same way —
 * a `bonuses` row plus a WalletService credit — so wallet history stays
 * consistent with every other income.
 */
class BonusService
{
    public function __construct(
        private MemberStatsService $stats,
        private WalletService $wallets,
    ) {}

    /**
     * Pay every active leadership/sales rule the member now meets and hasn't
     * been paid for. Safe to run repeatedly.
     *
     * @return list<Bonus> bonuses paid by this run
     */
    public function evaluateThresholds(Member $member, ?MemberStats $stats = null): array
    {
        if ($member->status !== MemberStatus::Active) {
            return [];
        }

        $stats ??= $this->stats->forMember($member);
        $paid = [];

        $rules = BonusRule::query()
            ->where('is_active', true)
            ->whereNotIn('id', $member->bonuses()->whereNotNull('bonus_rule_id')->select('bonus_rule_id'))
            ->orderBy('threshold')
            ->get();

        foreach ($rules as $rule) {
            $value = $rule->type === BonusRule::LEADERSHIP ? $stats->activeTeam : $stats->personalSales;

            if ($value < $rule->threshold) {
                continue;
            }

            $bonus = $this->pay(
                $member,
                $rule->type === BonusRule::LEADERSHIP ? BonusType::Leadership : BonusType::Sales,
                $rule->amount,
                $rule->name,
                $rule,
            );

            if ($bonus !== null) {
                $paid[] = $bonus;
            }
        }

        return $paid;
    }

    /**
     * One-off performance bonus decided by an admin.
     */
    public function awardPerformance(Member $member, int $amount, string $reason, Admin $admin): Bonus
    {
        if ($amount <= 0 || trim($reason) === '') {
            throw new MemberAdminException('A positive amount and a reason are required.');
        }

        if ($member->status !== MemberStatus::Active) {
            throw new MemberAdminException('Only active members can receive a performance bonus.');
        }

        $bonus = $this->pay($member, BonusType::Performance, $amount, "Performance bonus: {$reason}", null, $admin);

        return $bonus ?? throw new MemberAdminException('The bonus could not be recorded.');
    }

    private function pay(Member $member, BonusType $type, int $amount, string $description, ?BonusRule $rule, ?Admin $admin = null): ?Bonus
    {
        try {
            return DB::transaction(function () use ($member, $type, $amount, $description, $rule, $admin) {
                $bonus = Bonus::query()->create([
                    'member_id' => $member->id,
                    'bonus_rule_id' => $rule?->id,
                    'type' => $type,
                    'amount' => $amount,
                    'cycle_date' => today(),
                    'status' => PayoutStatus::Paid,
                    'description' => $description,
                    'awarded_by' => $admin?->id,
                ]);

                $walletType = match ($type) {
                    BonusType::Leadership => WalletTransactionType::LeadershipBonus,
                    BonusType::Sales => WalletTransactionType::SalesBonus,
                    default => WalletTransactionType::PerformanceBonus,
                };

                $this->wallets->credit($member, $amount, $walletType, $bonus, $description);

                activity('bonuses')
                    ->performedOn($member)
                    ->causedBy($admin)
                    ->withProperties(['bonus_id' => $bonus->id, 'type' => $type->value, 'amount' => $amount, 'rule_id' => $rule?->id])
                    ->log('Bonus paid');

                return $bonus;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            return null; // a concurrent run already paid this rule
        }
    }
}
