<?php

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Enums\MemberStatus;
use App\Enums\PayoutStatus;
use App\Enums\SaleStatus;
use App\Enums\WithdrawalStatus;
use App\Models\Bonus;
use App\Models\Commission;
use App\Models\Expense;
use App\Models\IncomeTransaction;
use App\Models\Member;
use App\Models\Sale;
use App\Models\Withdrawal;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Company-level numbers for the admin dashboard. Money in poysha.
 *
 * Definitions (reused by the Phase 10 P&L report — keep them in one place):
 *  - revenue       = completed (non-refunded) sales + other income entries
 *  - cost of goods = packages.cost_of_goods for each completed sale
 *  - commission    = net commissions (paid + reversal rows) by cycle date
 *  - expenses      = expense entries, EXCLUDING the product_cost and
 *                    commission categories, which are derived automatically
 *                    above and would otherwise be counted twice
 *  - gross profit  = revenue − cost of goods
 *  - net profit    = gross profit − commission − expenses
 */
class AdminDashboardService
{
    public const PERIODS = ['today' => 'Today', 'month' => 'This month', 'year' => 'This year', 'all' => 'All time'];

    /**
     * Expense categories already covered by automatic figures.
     *
     * @return list<ExpenseCategory>
     */
    public static function derivedExpenseCategories(): array
    {
        return [ExpenseCategory::ProductCost, ExpenseCategory::Commission];
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}|null null = all time
     */
    public function range(string $period, ?CarbonInterface $now = null): ?array
    {
        $now ??= now();

        return match ($period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'year' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'all' => null,
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        };
    }

    /**
     * @return array<string, int>
     */
    public function metrics(string $period = 'month', ?CarbonInterface $now = null): array
    {
        $now ??= now();

        return $this->metricsForRange($this->range($period, $now), $now);
    }

    /**
     * @param  array{0: CarbonInterface, 1: CarbonInterface}|null  $range  null = all time
     * @return array<string, int>
     */
    public function metricsForRange(?array $range, ?CarbonInterface $now = null): array
    {
        $now ??= now();

        $pnl = $this->pnlForRange($range);
        $completedSales = fn () => $this->within(Sale::query()->where('sales.status', SaleStatus::Completed), 'sales.created_at', $range);

        $today = [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
        $openWithdrawals = Withdrawal::query()->whereIn('status', [
            WithdrawalStatus::Pending, WithdrawalStatus::Approved, WithdrawalStatus::Processing,
        ]);

        return [
            // Members (totals are all-time; "new" follows the period).
            'members_total' => Member::query()->count(),
            'members_active' => Member::query()->where('status', MemberStatus::Active)->count(),
            'members_pending' => Member::query()->where('status', MemberStatus::Pending)->count(),
            'members_new' => $this->within(Member::query()->whereNotNull('activated_at'), 'activated_at', $range)->count(),

            // Sales
            'sales_amount' => $pnl['sales_amount'],
            'sales_count' => $completedSales()->count(),
            'sales_today_amount' => (int) $this->within(Sale::query()->where('status', SaleStatus::Completed), 'created_at', $today)->sum('amount'),
            'sales_today_count' => $this->within(Sale::query()->where('status', SaleStatus::Completed), 'created_at', $today)->count(),
            'refunded_amount' => (int) $this->within(Sale::query()->where('status', SaleStatus::Refunded), 'refunded_at', $range)->sum('amount'),

            // Payouts
            'commission_paid' => $pnl['commission_paid'],
            'withdrawals_open_amount' => (int) (clone $openWithdrawals)->sum('amount'),
            'withdrawals_open_count' => (clone $openWithdrawals)->count(),

            // P&L
            'revenue' => $pnl['revenue'],
            'other_income' => $pnl['other_income'],
            'cost_of_goods' => $pnl['cost_of_goods'],
            'expenses' => $pnl['expenses'],
            'gross_profit' => $pnl['gross_profit'],
            'net_profit' => $pnl['net_profit'],
        ];
    }

    /**
     * The profit & loss figures alone — the single definition shared by the
     * dashboard and the P&L report (which calls it once per period row).
     *
     * @param  array{0: CarbonInterface, 1: CarbonInterface}|null  $range  null = all time
     * @return array{sales_amount: int, other_income: int, revenue: int, cost_of_goods: int, gross_profit: int, commission_paid: int, expenses: int, net_profit: int}
     */
    public function pnlForRange(?array $range): array
    {
        $completedSales = fn () => $this->within(Sale::query()->where('sales.status', SaleStatus::Completed), 'sales.created_at', $range);

        $salesAmount = (int) $completedSales()->sum('sales.amount');
        $otherIncome = (int) $this->within(IncomeTransaction::query(), 'date', $range, dateOnly: true)->sum('amount');
        $costOfGoods = (int) $completedSales()->join('packages', 'packages.id', '=', 'sales.package_id')->sum('packages.cost_of_goods');
        // Commissions (net of reversals) plus leadership/sales/performance bonuses.
        $commission = (int) $this->within(
            Commission::query()->whereIn('status', [PayoutStatus::Paid, PayoutStatus::Reversed]),
            'cycle_date', $range, dateOnly: true,
        )->sum('amount') + (int) $this->within(
            Bonus::query()->where('status', PayoutStatus::Paid),
            'cycle_date', $range, dateOnly: true,
        )->sum('amount');
        $expenses = (int) $this->within(
            Expense::query()->whereNotIn('category', self::derivedExpenseCategories()),
            'date', $range, dateOnly: true,
        )->sum('amount');

        $revenue = $salesAmount + $otherIncome;
        $grossProfit = $revenue - $costOfGoods;

        return [
            'sales_amount' => $salesAmount,
            'other_income' => $otherIncome,
            'revenue' => $revenue,
            'cost_of_goods' => $costOfGoods,
            'gross_profit' => $grossProfit,
            'commission_paid' => $commission,
            'expenses' => $expenses,
            'net_profit' => $grossProfit - $commission - $expenses,
        ];
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array{0: CarbonInterface, 1: CarbonInterface}|null  $range
     * @return Builder<TModel>
     */
    private function within(Builder $query, string $column, ?array $range, bool $dateOnly = false): Builder
    {
        if ($range === null) {
            return $query;
        }

        [$from, $to] = $range;

        return $dateOnly
            ? $query->whereBetween($column, [$from->toDateString(), $to->toDateString()])
            : $query->whereBetween($column, [$from, $to]);
    }
}
