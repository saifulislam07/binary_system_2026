<?php

namespace App\Services;

use App\Enums\ExpenseCategory;
use App\Enums\MemberStatus;
use App\Enums\PayoutStatus;
use App\Enums\SaleStatus;
use App\Enums\WithdrawalStatus;
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
        $range = $this->range($period, $now);

        $completedSales = fn () => $this->within(Sale::query()->where('sales.status', SaleStatus::Completed), 'sales.created_at', $range);

        $salesAmount = (int) $completedSales()->sum('sales.amount');
        $otherIncome = (int) $this->within(IncomeTransaction::query(), 'date', $range, dateOnly: true)->sum('amount');
        $costOfGoods = (int) $completedSales()->join('packages', 'packages.id', '=', 'sales.package_id')->sum('packages.cost_of_goods');
        $commission = (int) $this->within(
            Commission::query()->whereIn('status', [PayoutStatus::Paid, PayoutStatus::Reversed]),
            'cycle_date', $range, dateOnly: true,
        )->sum('amount');
        $expenses = (int) $this->within(
            Expense::query()->whereNotIn('category', self::derivedExpenseCategories()),
            'date', $range, dateOnly: true,
        )->sum('amount');

        $revenue = $salesAmount + $otherIncome;
        $grossProfit = $revenue - $costOfGoods;

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
            'sales_amount' => $salesAmount,
            'sales_count' => $completedSales()->count(),
            'sales_today_amount' => (int) $this->within(Sale::query()->where('status', SaleStatus::Completed), 'created_at', $today)->sum('amount'),
            'sales_today_count' => $this->within(Sale::query()->where('status', SaleStatus::Completed), 'created_at', $today)->count(),
            'refunded_amount' => (int) $this->within(Sale::query()->where('status', SaleStatus::Refunded), 'refunded_at', $range)->sum('amount'),

            // Payouts
            'commission_paid' => $commission,
            'withdrawals_open_amount' => (int) (clone $openWithdrawals)->sum('amount'),
            'withdrawals_open_count' => (clone $openWithdrawals)->count(),

            // P&L
            'revenue' => $revenue,
            'other_income' => $otherIncome,
            'cost_of_goods' => $costOfGoods,
            'expenses' => $expenses,
            'gross_profit' => $grossProfit,
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
