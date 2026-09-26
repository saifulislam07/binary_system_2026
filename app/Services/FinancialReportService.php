<?php

namespace App\Services;

use App\Enums\CommissionType;
use App\Enums\PayoutStatus;
use App\Enums\SaleStatus;
use App\Enums\WithdrawalStatus;
use App\Models\Bonus;
use App\Models\Commission;
use App\Models\Package;
use App\Models\Sale;
use App\Models\Withdrawal;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Period-bucketed admin reports. Every report returns the same shape so one
 * view and one CSV exporter render them all:
 *
 *   ['title' => string,
 *    'columns' => list<array{label: string, money: bool}>,
 *    'rows' => list<array{period: string, values: list<int>}>,
 *    'totals' => list<int>]
 *
 * Money values are poysha; P&L figures come from AdminDashboardService so
 * the report and the dashboard can never disagree.
 */
class FinancialReportService
{
    public const REPORTS = [
        'pnl' => 'Profit & Loss',
        'commission' => 'Commission',
        'withdrawal' => 'Withdrawals',
        'sales' => 'Sales',
    ];

    public const GRANULARITIES = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'];

    public const MAX_BUCKETS = 400;

    public function __construct(private AdminDashboardService $dashboard) {}

    /**
     * @return array{title: string, columns: list<array{label: string, money: bool}>, rows: list<array{period: string, values: list<int>}>, totals: list<int>}
     */
    public function build(string $report, string $granularity, CarbonInterface $from, CarbonInterface $to): array
    {
        $buckets = $this->buckets($granularity, $from, $to);

        [$columns, $valuesFor] = match ($report) {
            'pnl' => $this->pnl(),
            'commission' => $this->commission(),
            'withdrawal' => $this->withdrawal(),
            'sales' => $this->sales(),
            default => throw new InvalidArgumentException("Unknown report [{$report}]."),
        };

        $rows = [];
        $totals = array_fill(0, count($columns), 0);

        foreach ($buckets as [$label, $start, $end]) {
            $values = $valuesFor($start, $end);
            $rows[] = ['period' => $label, 'values' => $values];

            foreach ($values as $i => $value) {
                $totals[$i] += $value;
            }
        }

        return ['title' => self::REPORTS[$report], 'columns' => $columns, 'rows' => $rows, 'totals' => array_values($totals)];
    }

    /**
     * @return list<array{0: string, 1: CarbonInterface, 2: CarbonInterface}>
     */
    public function buckets(string $granularity, CarbonInterface $from, CarbonInterface $to): array
    {
        if (! array_key_exists($granularity, self::GRANULARITIES)) {
            throw new InvalidArgumentException("Unknown granularity [{$granularity}].");
        }

        $weekStart = (int) config('business.week_starts_on', CarbonInterface::SATURDAY);
        $cursor = match ($granularity) {
            'daily' => $from->copy()->startOfDay(),
            'weekly' => $from->copy()->startOfWeek($weekStart),
            'monthly' => $from->copy()->startOfMonth(),
            'yearly' => $from->copy()->startOfYear(),
        };

        $buckets = [];

        while ($cursor->lte($to) && count($buckets) < self::MAX_BUCKETS) {
            [$end, $label] = match ($granularity) {
                'daily' => [$cursor->copy()->endOfDay(), $cursor->format('Y-m-d')],
                'weekly' => [$cursor->copy()->addDays(6)->endOfDay(), 'Week of '.$cursor->format('Y-m-d')],
                'monthly' => [$cursor->copy()->endOfMonth(), $cursor->format('Y-m')],
                'yearly' => [$cursor->copy()->endOfYear(), $cursor->format('Y')],
            };

            $buckets[] = [$label, $cursor->copy(), $end];

            $cursor = match ($granularity) {
                'daily' => $cursor->copy()->addDay(),
                'weekly' => $cursor->copy()->addWeek(),
                'monthly' => $cursor->copy()->addMonth(),
                'yearly' => $cursor->copy()->addYear(),
            };
        }

        return $buckets;
    }

    /**
     * @return array{0: list<array{label: string, money: bool}>, 1: callable(CarbonInterface, CarbonInterface): list<int>}
     */
    private function pnl(): array
    {
        $keys = [
            'revenue' => 'Revenue', 'cost_of_goods' => 'Cost of goods', 'gross_profit' => 'Gross profit',
            'commission_paid' => 'Commission', 'expenses' => 'Expenses', 'net_profit' => 'Net profit',
        ];

        return [
            array_map(fn (string $label) => ['label' => $label, 'money' => true], array_values($keys)),
            function (CarbonInterface $start, CarbonInterface $end) use ($keys) {
                $m = $this->dashboard->pnlForRange([$start, $end]);

                return array_map(fn (string $key) => $m[$key], array_keys($keys));
            },
        ];
    }

    /**
     * @return array{0: list<array{label: string, money: bool}>, 1: callable(CarbonInterface, CarbonInterface): list<int>}
     */
    private function commission(): array
    {
        $types = CommissionType::cases();

        return [
            [
                ...array_map(fn (CommissionType $t) => ['label' => ucfirst($t->value), 'money' => true], $types),
                ['label' => 'Total (net)', 'money' => true],
                ['label' => 'Voided over cap', 'money' => true],
            ],
            function (CarbonInterface $start, CarbonInterface $end) use ($types) {
                $net = Commission::query()
                    ->whereIn('status', [PayoutStatus::Paid, PayoutStatus::Reversed])
                    ->whereBetween('cycle_date', [$start->toDateString(), $end->toDateString()])
                    ->selectRaw('type, SUM(amount) AS total')
                    ->groupBy('type')
                    ->pluck('total', 'type');

                $voided = (int) Commission::query()
                    ->where('status', PayoutStatus::Voided)
                    ->whereBetween('cycle_date', [$start->toDateString(), $end->toDateString()])
                    ->sum('amount');

                // Leadership / sales / performance are paid from the bonuses table.
                $bonuses = Bonus::query()
                    ->where('status', PayoutStatus::Paid)
                    ->whereBetween('cycle_date', [$start->toDateString(), $end->toDateString()])
                    ->selectRaw('type, SUM(amount) AS total')
                    ->groupBy('type')
                    ->pluck('total', 'type');

                $byType = array_map(fn (CommissionType $t) => (int) ($net[$t->value] ?? 0) + (int) ($bonuses[$t->value] ?? 0), $types);

                return [...$byType, array_sum($byType), $voided];
            },
        ];
    }

    /**
     * @return array{0: list<array{label: string, money: bool}>, 1: callable(CarbonInterface, CarbonInterface): list<int>}
     */
    private function withdrawal(): array
    {
        return [
            [
                ['label' => 'Requested (count)', 'money' => false],
                ['label' => 'Requested', 'money' => true],
                ['label' => 'Paid', 'money' => true],
                ['label' => 'Rejected', 'money' => true],
            ],
            function (CarbonInterface $start, CarbonInterface $end) {
                $requested = Withdrawal::query()->whereBetween('created_at', [$start, $end]);
                $settled = fn (WithdrawalStatus $status) => (int) Withdrawal::query()
                    ->where('status', $status)
                    ->whereBetween('processed_at', [$start, $end])
                    ->sum('amount');

                return [
                    (clone $requested)->count(),
                    (int) (clone $requested)->sum('amount'),
                    $settled(WithdrawalStatus::Paid),
                    $settled(WithdrawalStatus::Rejected),
                ];
            },
        ];
    }

    /**
     * @return array{0: list<array{label: string, money: bool}>, 1: callable(CarbonInterface, CarbonInterface): list<int>}
     */
    private function sales(): array
    {
        $packages = Package::query()->orderBy('sort_order')->get(['id', 'name']);

        return [
            [
                ...$packages->map(fn (Package $p) => ['label' => $p->name, 'money' => true])->all(),
                ['label' => 'Orders', 'money' => false],
                ['label' => 'Total sales', 'money' => true],
                ['label' => 'Refunded', 'money' => true],
            ],
            function (CarbonInterface $start, CarbonInterface $end) use ($packages) {
                $byPackage = Sale::query()
                    ->where('status', SaleStatus::Completed)
                    ->whereBetween('created_at', [$start, $end])
                    ->selectRaw('package_id, COUNT(*) AS n, SUM(amount) AS total')
                    ->groupBy('package_id')
                    ->get()
                    ->keyBy('package_id');

                $amounts = $packages->map(fn (Package $p) => (int) ($byPackage[$p->id]->total ?? 0))->all();

                return [
                    ...$amounts,
                    (int) $byPackage->sum('n'),
                    array_sum($amounts),
                    (int) Sale::query()->where('status', SaleStatus::Refunded)->whereBetween('refunded_at', [$start, $end])->sum('amount'),
                ];
            },
        ];
    }
}
