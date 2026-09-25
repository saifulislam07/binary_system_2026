<?php

namespace App\Services;

use App\Enums\PayoutStatus;
use App\Enums\SaleStatus;
use App\Models\Commission;
use App\Models\Member;
use Carbon\CarbonInterface;

/**
 * Numbers for the member dashboard overview. Money in poysha, volumes in
 * whole BV.
 */
class MemberOverviewService
{
    public const CHART_PERIODS = 6;

    public function __construct(
        private IncomeSummaryService $income,
        private TeamService $team,
    ) {}

    /**
     * @return array{
     *     total_income: int, available: int, personal_sales: int,
     *     left_team_bv: int, right_team_bv: int,
     *     team_size: int, active_team: int,
     *     left_team: array{total: int, active: int}, right_team: array{total: int, active: int},
     *     chart: list<array{label: string, from: string, to: string, amount: int}>
     * }
     */
    public function for(Member $member, ?CarbonInterface $now = null): array
    {
        $summary = $this->income->for($member, $now);
        $legs = $this->team->legCounts($member);
        $node = $member->binaryNode()->first();

        return [
            'total_income' => $summary['lifetime'],
            'available' => $summary['available'],
            'personal_sales' => (int) $member->sales()->where('status', SaleStatus::Completed)->sum('amount'),
            'left_team_bv' => intdiv($node->left_lifetime_volume ?? 0, 100),
            'right_team_bv' => intdiv($node->right_lifetime_volume ?? 0, 100),
            'team_size' => $legs['left']['total'] + $legs['right']['total'],
            'active_team' => $legs['left']['active'] + $legs['right']['active'],
            'left_team' => $legs['left'],
            'right_team' => $legs['right'],
            'chart' => $this->incomeByPeriod($member, $now ?? now()),
        ];
    }

    /**
     * Net income (all commission types, after reversals) for the last six
     * cycle periods — days or weeks, per COMMISSION_CYCLE — oldest first.
     *
     * @return list<array{label: string, from: string, to: string, amount: int}>
     */
    public function incomeByPeriod(Member $member, CarbonInterface $now): array
    {
        $weekly = config('business.commission_cycle') === 'weekly';
        $weekStart = (int) config('business.week_starts_on', CarbonInterface::SATURDAY);

        $periods = [];

        for ($i = self::CHART_PERIODS - 1; $i >= 0; $i--) {
            $from = $weekly
                ? $now->copy()->startOfWeek($weekStart)->subWeeks($i)
                : $now->copy()->startOfDay()->subDays($i);
            $to = $weekly ? $from->copy()->addDays(6) : $from->copy();

            $periods[] = [
                'label' => $weekly ? 'Wk '.$from->format('j M') : $from->format('j M'),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'amount' => 0,
            ];
        }

        $totals = Commission::query()
            ->where('member_id', $member->id)
            ->whereIn('status', [PayoutStatus::Paid, PayoutStatus::Reversed])
            ->whereBetween('cycle_date', [$periods[0]['from'], $periods[self::CHART_PERIODS - 1]['to']])
            ->selectRaw('cycle_date, SUM(amount) AS total')
            ->groupBy('cycle_date')
            ->pluck('total', 'cycle_date');

        foreach ($totals as $date => $total) {
            $date = substr((string) $date, 0, 10);

            foreach ($periods as &$period) {
                if ($date >= $period['from'] && $date <= $period['to']) {
                    $period['amount'] += (int) $total;
                    break;
                }
            }
            unset($period);
        }

        return $periods;
    }
}
