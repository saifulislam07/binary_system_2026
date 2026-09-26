<?php

namespace App\Services;

use App\Enums\CommissionCycleStatus;
use App\Enums\CommissionType;
use App\Enums\MemberStatus;
use App\Enums\PayoutStatus;
use App\Enums\PlacementSide;
use App\Enums\WalletTransactionType;
use App\Exceptions\MatchingException;
use App\Models\BinaryNode;
use App\Models\Commission;
use App\Models\CommissionCycle;
use App\Models\CommissionRule;
use App\Models\Member;
use App\Models\TeamVolume;
use App\Models\VolumeConsumption;
use App\Models\VolumeLot;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Rules #5 and #6: binary matching and commission, once per cycle.
 *
 * Per member: matched = min(left, right), consumed FIFO from both sides'
 * volume lots. Unmatched volume carries forward, or is flushed when
 * carry-forward is off. Commission = matched × rate, limited by the
 * daily/weekly/monthly caps (summed from already-paid binary commissions);
 * the excess is voided or deferred to later cycles per cap_overflow_behavior.
 */
class MatchingService
{
    public const OWN_COMMISSION = 'Binary commission';

    public const RELEASED_COMMISSION = 'Carried-forward binary commission released';

    public const VOIDED_COMMISSION = 'Binary commission above cap (voided)';

    private const LOT_BATCH = 500;

    public function __construct(private WalletService $wallets) {}

    public function runCycle(CarbonInterface $cycleDate): CommissionCycle
    {
        $date = $cycleDate->copy()->startOfDay();

        $laterClosed = CommissionCycle::query()
            ->where('cycle_date', '>', $date->toDateString())
            ->where('status', CommissionCycleStatus::Closed)
            ->exists();

        if ($laterClosed) {
            throw new MatchingException("A cycle after {$date->toDateString()} is already closed.");
        }

        $cycle = CommissionCycle::query()->firstOrCreate(
            ['cycle_date' => $date->toDateString()],
            ['status' => CommissionCycleStatus::Running],
        );

        if ($cycle->status === CommissionCycleStatus::Closed) {
            return $cycle; // already done
        }

        $rules = $this->rules();

        $this->candidates($rules['carry_forward'])->chunkById(500, function ($nodes) use ($cycle, $date, $rules) {
            foreach ($nodes as $node) {
                $this->processMember((int) $node->member_id, $cycle, $date, $rules);
            }
        }, 'binary_nodes.id', 'id');

        $cycle->forceFill(['status' => CommissionCycleStatus::Closed, 'closed_at' => now()])->save();

        activity('commission')
            ->performedOn($cycle)
            ->withProperties([
                'members' => $cycle->teamVolumes()->count(),
                'matched_bv' => (int) $cycle->teamVolumes()->sum('matched_volume'),
                'paid' => (int) $cycle->teamVolumes()->sum(DB::raw('paid_commission + deferred_released')),
                'rules' => $rules,
            ])
            ->log('Commission cycle closed');

        return $cycle;
    }

    /**
     * @param  array{rate: int, daily_cap: int, weekly_cap: int, monthly_cap: int, carry_forward: bool, overflow: string}  $rules
     */
    public function processMember(int $memberId, CommissionCycle $cycle, CarbonInterface $date, array $rules): ?TeamVolume
    {
        return DB::transaction(function () use ($memberId, $cycle, $date, $rules) {
            $node = BinaryNode::query()->where('member_id', $memberId)->lockForUpdate()->firstOrFail();

            if (TeamVolume::query()->where('member_id', $memberId)->where('commission_cycle_id', $cycle->id)->exists()) {
                return null; // already processed in an earlier (interrupted) run
            }

            $left = $node->left_volume;
            $right = $node->right_volume;
            $matched = min($left, $right);
            $flushLeft = $rules['carry_forward'] ? 0 : $left - $matched;
            $flushRight = $rules['carry_forward'] ? 0 : $right - $matched;

            if ($matched === 0 && $flushLeft === 0 && $flushRight === 0 && $node->deferred_commission === 0) {
                return null;
            }

            $teamVolume = TeamVolume::query()->create([
                'member_id' => $memberId,
                'commission_cycle_id' => $cycle->id,
                'left_volume' => $left,
                'right_volume' => $right,
                'matched_volume' => $matched,
                'carried_left' => $left - $matched - $flushLeft,
                'carried_right' => $right - $matched - $flushRight,
                'flushed_left' => $flushLeft,
                'flushed_right' => $flushRight,
                'carry_forward_enabled' => $rules['carry_forward'],
            ]);

            $this->consume($memberId, PlacementSide::Left, $matched, $teamVolume, VolumeConsumption::MATCHED);
            $this->consume($memberId, PlacementSide::Right, $matched, $teamVolume, VolumeConsumption::MATCHED);
            $this->consume($memberId, PlacementSide::Left, $flushLeft, $teamVolume, VolumeConsumption::FLUSHED);
            $this->consume($memberId, PlacementSide::Right, $flushRight, $teamVolume, VolumeConsumption::FLUSHED);

            $node->left_volume = $node->left_volume_carry = $teamVolume->carried_left;
            $node->right_volume = $node->right_volume_carry = $teamVolume->carried_right;

            $this->payCommission($node, $teamVolume, $cycle, $date, $rules);

            $node->save();

            return $teamVolume;
        }, 3);
    }

    /**
     * Pay previously deferred commission first (oldest money first), then
     * this cycle's own commission, both within the remaining cap room.
     *
     * @param  array{rate: int, daily_cap: int, weekly_cap: int, monthly_cap: int, carry_forward: bool, overflow: string}  $rules
     */
    private function payCommission(BinaryNode $node, TeamVolume $teamVolume, CommissionCycle $cycle, CarbonInterface $date, array $rules): void
    {
        $member = Member::query()->findOrFail($node->member_id);

        if ($member->status !== MemberStatus::Active) {
            // Candidates are active members; this only happens if a member was suspended mid-run.
            return;
        }

        $room = $this->capRoom($member->id, $date, $rules); // null = no cap

        $released = $room === null ? $node->deferred_commission : min($node->deferred_commission, $room);

        if ($released > 0) {
            $this->payRow($member, $teamVolume, $cycle, $date, $released, self::RELEASED_COMMISSION);
            $node->deferred_commission -= $released;
            $room = $room === null ? null : $room - $released;
        }

        $gross = Money::percentOf($teamVolume->matched_volume, $rules['rate']);
        $paid = $room === null ? $gross : min($gross, $room);
        $overflow = $gross - $paid;

        if ($paid > 0) {
            $this->payRow($member, $teamVolume, $cycle, $date, $paid, self::OWN_COMMISSION);
        }

        if ($overflow > 0) {
            if ($rules['overflow'] === 'carry_forward') {
                $node->deferred_commission += $overflow;
            } else {
                Commission::query()->create([
                    'member_id' => $member->id,
                    'type' => CommissionType::Binary,
                    'commission_cycle_id' => $cycle->id,
                    'team_volume_id' => $teamVolume->id,
                    'amount' => $overflow,
                    'cycle_date' => $date->toDateString(),
                    'status' => PayoutStatus::Voided,
                    'description' => self::VOIDED_COMMISSION,
                ]);
            }
        }

        $teamVolume->forceFill([
            'gross_commission' => $gross,
            'paid_commission' => $paid,
            'overflow_commission' => $overflow,
            'overflow_action' => $overflow > 0 ? $rules['overflow'] : null,
            'deferred_released' => $released,
        ])->save();
    }

    private function payRow(Member $member, TeamVolume $teamVolume, CommissionCycle $cycle, CarbonInterface $date, int $amount, string $description): void
    {
        $commission = Commission::query()->create([
            'member_id' => $member->id,
            'type' => CommissionType::Binary,
            'commission_cycle_id' => $cycle->id,
            'team_volume_id' => $teamVolume->id,
            'amount' => $amount,
            'cycle_date' => $date->toDateString(),
            'status' => PayoutStatus::Paid,
            'description' => $description,
        ]);

        $this->wallets->credit($member, $amount, WalletTransactionType::BinaryCommission, $commission, "{$description} ({$date->toDateString()})");
    }

    /**
     * Remaining binary commission the member may still be paid on `$date`,
     * i.e. the tightest of the daily/weekly/monthly windows. A cap of 0 or
     * less means "no cap". Returns null when no cap applies.
     *
     * @param  array{daily_cap: int, weekly_cap: int, monthly_cap: int}  $rules
     */
    public function capRoom(int $memberId, CarbonInterface $date, array $rules): ?int
    {
        $weekStart = (int) config('business.week_starts_on', CarbonInterface::SATURDAY);

        $windows = array_filter([
            [$rules['daily_cap'], $date->copy()->startOfDay()->toDateString(), $date->copy()->endOfDay()->toDateString()],
            [$rules['weekly_cap'], $date->copy()->startOfWeek($weekStart)->toDateString(), $date->copy()->endOfWeek(($weekStart + 6) % 7)->toDateString()],
            [$rules['monthly_cap'], $date->copy()->startOfMonth()->toDateString(), $date->copy()->endOfMonth()->toDateString()],
        ], fn (array $window) => $window[0] > 0);

        if ($windows === []) {
            return null;
        }

        // One query for all windows: SUM(CASE WHEN cycle_date in window …) per cap.
        $select = [];
        $bindings = [];
        $earliest = $latest = $date->toDateString(); // Y-m-d strings compare chronologically

        foreach (array_values($windows) as $i => [, $from, $to]) {
            $select[] = "COALESCE(SUM(CASE WHEN cycle_date BETWEEN ? AND ? THEN amount ELSE 0 END), 0) AS w{$i}";
            array_push($bindings, $from, $to);
            $earliest = min($earliest, $from);
            $latest = max($latest, $to);
        }

        $used = (array) Commission::query()
            ->toBase()
            ->selectRaw(implode(', ', $select), $bindings)
            ->where('member_id', $memberId)
            ->where('type', CommissionType::Binary)
            ->where('status', PayoutStatus::Paid)
            ->whereBetween('cycle_date', [$earliest, $latest])
            ->first();

        $room = null;

        foreach (array_values($windows) as $i => [$cap]) {
            $windowRoom = max(0, $cap - (int) $used["w{$i}"]);
            $room = $room === null ? $windowRoom : min($room, $windowRoom);
        }

        return $room;
    }

    /**
     * Take `$amount` from the member's oldest open lots on `$side`.
     */
    private function consume(int $memberId, PlacementSide $side, int $amount, TeamVolume $teamVolume, string $kind): void
    {
        $left = $amount;

        while ($left > 0) {
            $lots = VolumeLot::query()
                ->where('member_id', $memberId)
                ->where('side', $side)
                ->where('remaining', '>', 0)
                ->orderBy('id')
                ->limit(self::LOT_BATCH)
                ->lockForUpdate()
                ->get();

            if ($lots->isEmpty()) {
                throw new MatchingException("Member [{$memberId}] {$side->value} volume exceeds its open lots by {$left}.");
            }

            $now = now();
            $rows = [];
            $emptied = [];

            foreach ($lots as $lot) {
                $take = min($lot->remaining, $left);

                // FIFO: every lot but possibly the last is used up entirely,
                // so those are zeroed in one statement below.
                if ($take === $lot->remaining) {
                    $emptied[] = $lot->id;
                } else {
                    $lot->decrement('remaining', $take);
                }

                $rows[] = [
                    'volume_lot_id' => $lot->id,
                    'team_volume_id' => $teamVolume->id,
                    'kind' => $kind,
                    'bv' => $take,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $left -= $take;

                if ($left === 0) {
                    break;
                }
            }

            if ($emptied !== []) {
                VolumeLot::query()->whereKey($emptied)->update(['remaining' => 0, 'updated_at' => $now]);
            }

            VolumeConsumption::query()->insert($rows);
        }
    }

    /**
     * Active members with something to do this cycle.
     *
     * @return Builder<BinaryNode>
     */
    private function candidates(bool $carryForward): Builder
    {
        return BinaryNode::query()
            ->select('binary_nodes.id', 'binary_nodes.member_id')
            ->join('members', 'members.id', '=', 'binary_nodes.member_id')
            ->where('members.status', MemberStatus::Active)
            ->where(function ($q) use ($carryForward) {
                $q->where(fn ($q) => $q->where('left_volume', '>', 0)->where('right_volume', '>', 0))
                    ->orWhere('deferred_commission', '>', 0);

                if (! $carryForward) {
                    $q->orWhere('left_volume', '>', 0)->orWhere('right_volume', '>', 0);
                }
            });
    }

    /**
     * @return array{rate: int, daily_cap: int, weekly_cap: int, monthly_cap: int, carry_forward: bool, overflow: string}
     */
    public function rules(): array
    {
        $overflow = CommissionRule::raw(CommissionRule::CAP_OVERFLOW_BEHAVIOR);

        if (! in_array($overflow, ['void', 'carry_forward'], true)) {
            throw new MatchingException("Invalid cap_overflow_behavior [{$overflow}].");
        }

        return [
            'rate' => CommissionRule::int(CommissionRule::BINARY_RATE_BPS),
            'daily_cap' => CommissionRule::int(CommissionRule::DAILY_CAP),
            'weekly_cap' => CommissionRule::int(CommissionRule::WEEKLY_CAP),
            'monthly_cap' => CommissionRule::int(CommissionRule::MONTHLY_CAP),
            'carry_forward' => CommissionRule::bool(CommissionRule::CARRY_FORWARD_ENABLED),
            'overflow' => $overflow,
        ];
    }
}
