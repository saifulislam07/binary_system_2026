<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ExpenseCategory;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Expense;
use App\Models\IncomeTransaction;
use App\Services\AdminDashboardService;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Manual company income and expense entries. Sales revenue, cost of goods
 * and commissions are recorded automatically — these screens are for
 * everything else (salaries, servers, marketing, other income…).
 */
class FinancialController extends Controller
{
    public const INCOME_SOURCES = ['other' => 'Other income', 'service' => 'Service fee', 'interest' => 'Bank interest', 'sponsorship' => 'Sponsorship'];

    public function index(Request $request): View
    {
        $tab = $request->query('tab') === 'income' ? 'income' : 'expenses';

        return view('admin.financial.index', [
            'tab' => $tab,
            'expenses' => Expense::query()->with('recordedBy:id,name')->latest('date')->latest('id')->paginate(20, ['*'], 'expenses_page')->withQueryString(),
            'incomes' => IncomeTransaction::query()->with('recordedBy:id,name')->latest('date')->latest('id')->paginate(20, ['*'], 'income_page')->withQueryString(),
            'categories' => ExpenseCategory::cases(),
            'derived' => array_map(fn (ExpenseCategory $c) => $c->value, AdminDashboardService::derivedExpenseCategories()),
            'sources' => self::INCOME_SOURCES,
        ]);
    }

    public function storeExpense(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::enum(ExpenseCategory::class)],
            'amount' => ['required', 'string'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $expense = Expense::query()->create([...$data, 'amount' => $this->poysha($data['amount']), 'recorded_by' => $this->admin($request)->id]);

        activity('finance')->performedOn($expense)->causedBy($this->admin($request))
            ->withProperties(['category' => $expense->category->value, 'amount' => $expense->amount, 'date' => $data['date']])
            ->log('Expense recorded');

        return redirect()->route('admin.financial.index', ['tab' => 'expenses'])->with('success', 'Expense recorded.');
    }

    public function storeIncome(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'source' => ['required', Rule::in(array_keys(self::INCOME_SOURCES))],
            'amount' => ['required', 'string'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $income = IncomeTransaction::query()->create([...$data, 'amount' => $this->poysha($data['amount']), 'recorded_by' => $this->admin($request)->id]);

        activity('finance')->performedOn($income)->causedBy($this->admin($request))
            ->withProperties(['source' => $income->source, 'amount' => $income->amount, 'date' => $data['date']])
            ->log('Income recorded');

        return redirect()->route('admin.financial.index', ['tab' => 'income'])->with('success', 'Income recorded.');
    }

    public function destroyExpense(Request $request, Expense $expense): RedirectResponse
    {
        activity('finance')->causedBy($this->admin($request))
            ->withProperties(['deleted' => $expense->only(['id', 'category', 'amount', 'date', 'description'])])
            ->log('Expense deleted');
        $expense->delete();

        return redirect()->route('admin.financial.index', ['tab' => 'expenses'])->with('success', 'Expense deleted.');
    }

    public function destroyIncome(Request $request, IncomeTransaction $income): RedirectResponse
    {
        activity('finance')->causedBy($this->admin($request))
            ->withProperties(['deleted' => $income->only(['id', 'source', 'amount', 'date', 'description'])])
            ->log('Income deleted');
        $income->delete();

        return redirect()->route('admin.financial.index', ['tab' => 'income'])->with('success', 'Income deleted.');
    }

    private function poysha(string $taka): int
    {
        try {
            $amount = Money::fromTaka($taka);
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages(['amount' => 'Enter an amount in taka, e.g. 1500 or 1500.50.']);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'The amount must be greater than zero.']);
        }

        return $amount;
    }

    private function admin(Request $request): Admin
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin, 403);

        return $admin;
    }
}
