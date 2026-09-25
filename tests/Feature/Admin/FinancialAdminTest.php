<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\CommissionRule;
use App\Models\Expense;
use App\Models\IncomeTransaction;
use App\Models\Setting;
use App\Services\AdminDashboardService;
use App\Services\MatchingService;
use Database\Seeders\DemoNetworkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class FinancialAdminTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-20 12:00:00');
        $this->admin = Admin::factory()->superAdmin()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_expenses_and_income_are_recorded_in_poysha_and_logged()
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.financial.expenses.store'), ['category' => 'server', 'amount' => '1,250.50', 'date' => '2026-09-19', 'description' => 'VPS'])
            ->assertSessionHas('success');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.financial.income.store'), ['source' => 'interest', 'amount' => '300', 'date' => '2026-09-18', 'description' => 'Bank interest'])
            ->assertSessionHas('success');

        $expense = Expense::query()->firstOrFail();
        $this->assertSame(125_050, $expense->amount);
        $this->assertSame($this->admin->id, $expense->recorded_by);
        $this->assertSame(30_000, IncomeTransaction::query()->firstOrFail()->amount);
        $this->assertSame(2, Activity::query()->where('log_name', 'finance')->count());
    }

    public function test_invalid_entries_are_rejected()
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.financial.expenses.store'), ['category' => 'yacht', 'amount' => 'lots', 'date' => '2030-01-01', 'description' => ''])
            ->assertSessionHasErrors(['category', 'date', 'description']);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.financial.expenses.store'), ['category' => 'server', 'amount' => 'lots', 'date' => '2026-09-19', 'description' => 'x'])
            ->assertSessionHasErrors('amount');

        $this->assertSame(0, Expense::query()->count());
    }

    public function test_deleting_an_entry_is_logged_with_its_contents()
    {
        $expense = Expense::factory()->create(['amount' => 99_900, 'description' => 'Typo entry']);

        $this->actingAs($this->admin, 'admin')->delete(route('admin.financial.expenses.destroy', $expense))->assertSessionHas('success');

        $this->assertModelMissing($expense);
        $log = Activity::query()->where('description', 'Expense deleted')->firstOrFail();
        $this->assertSame('Typo entry', $log->properties['deleted']['description']);
    }

    public function test_pnl_report_rows_match_the_dashboard_definitions()
    {
        $this->seed(DemoNetworkSeeder::class);
        app(MatchingService::class)->runCycle(Carbon::parse('2026-09-20'));
        Expense::factory()->create(['category' => 'marketing', 'amount' => 500_000, 'date' => '2026-09-19']);

        $data = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.reports.index', ['report' => 'pnl', 'granularity' => 'daily', 'from' => '2026-09-19', 'to' => '2026-09-20']))
            ->assertOk()
            ->viewData('data');

        $this->assertCount(2, $data['rows']);
        $this->assertSame('2026-09-20', $data['rows'][1]['period']);

        $net = array_search('Net profit', array_column($data['columns'], 'label'), true);
        $dashboard = app(AdminDashboardService::class)->metricsForRange([Carbon::parse('2026-09-19')->startOfDay(), Carbon::parse('2026-09-20')->endOfDay()]);
        $this->assertSame($dashboard['net_profit'], $data['totals'][$net]);
        $this->assertSame(-500_000, $data['rows'][0]['values'][$net], 'Only the expense on the 19th');
    }

    public function test_commission_and_sales_reports_add_up()
    {
        $this->seed(DemoNetworkSeeder::class);
        app(MatchingService::class)->runCycle(Carbon::parse('2026-09-20'));

        $commission = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.reports.index', ['report' => 'commission', 'granularity' => 'monthly', 'from' => '2026-09-01', 'to' => '2026-09-30']))
            ->viewData('data');
        $labels = array_column($commission['columns'], 'label');
        $this->assertSame(1_020_000, $commission['totals'][array_search('Referral', $labels, true)]);
        $this->assertSame(1_540_000, $commission['totals'][array_search('Binary', $labels, true)]);
        $this->assertSame(2_560_000, $commission['totals'][array_search('Total (net)', $labels, true)]);

        $sales = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.reports.index', ['report' => 'sales', 'granularity' => 'yearly', 'from' => '2026-01-01', 'to' => '2026-12-31']))
            ->viewData('data');
        $labels = array_column($sales['columns'], 'label');
        $this->assertSame(20, $sales['totals'][array_search('Orders', $labels, true)]);
        $this->assertSame(20_500_000, $sales['totals'][array_search('Total sales', $labels, true)]);
        $this->assertSame(12_500_000, $sales['totals'][array_search('Business', $labels, true)]);
    }

    public function test_reports_export_as_csv()
    {
        Expense::factory()->create(['category' => 'server', 'amount' => 125_050, 'date' => '2026-09-20']);

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.reports.export', ['report' => 'pnl', 'granularity' => 'daily', 'from' => '2026-09-20', 'to' => '2026-09-20']))
            ->assertOk()
            ->assertDownload('pnl-daily-2026-09-20-to-2026-09-20.csv');

        $lines = array_map('str_getcsv', preg_split('/\R/', trim(ltrim($response->streamedContent(), "\xEF\xBB\xBF"))));
        $this->assertSame(['Period', 'Revenue (BDT)', 'Cost of goods (BDT)', 'Gross profit (BDT)', 'Commission (BDT)', 'Expenses (BDT)', 'Net profit (BDT)'], $lines[0]);
        $this->assertSame(['2026-09-20', '0.00', '0.00', '0.00', '0.00', '1250.50', '-1250.50'], $lines[1]);
        $this->assertSame('Total', $lines[2][0]);
    }

    public function test_settings_are_saved_as_basis_points_and_poysha_and_logged()
    {
        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.settings.update'), [
                'binary_rate' => '12.5', 'referral_rate' => '5',
                'daily_cap' => '6000', 'weekly_cap' => '25000', 'monthly_cap' => '0',
                'carry_forward_enabled' => '0', 'cap_overflow_behavior' => 'carry_forward',
                'min_withdrawal' => '1500',
            ])
            ->assertSessionHas('success');

        $this->assertSame(1250, CommissionRule::int(CommissionRule::BINARY_RATE_BPS));
        $this->assertSame(600_000, CommissionRule::int(CommissionRule::DAILY_CAP));
        $this->assertSame(0, CommissionRule::int(CommissionRule::MONTHLY_CAP));
        $this->assertFalse(CommissionRule::bool(CommissionRule::CARRY_FORWARD_ENABLED));
        $this->assertSame('carry_forward', CommissionRule::raw(CommissionRule::CAP_OVERFLOW_BEHAVIOR));
        $this->assertSame(150_000, Setting::int(Setting::MIN_WITHDRAWAL));

        $log = Activity::query()->where('description', 'Business settings changed')->firstOrFail();
        $this->assertSame('1000', $log->properties['old'][CommissionRule::BINARY_RATE_BPS]);
        $this->assertSame('1250', $log->properties['attributes'][CommissionRule::BINARY_RATE_BPS]);
        $this->assertArrayNotHasKey(CommissionRule::REFERRAL_RATE_BPS, $log->properties['attributes'], 'Unchanged values are not logged');
    }

    public function test_finance_role_sees_reports_but_not_settings()
    {
        $finance = Admin::factory()->create()->assignRole('finance');

        $this->actingAs($finance, 'admin')->get(route('admin.reports.index'))->assertOk();
        $this->actingAs($finance, 'admin')->get(route('admin.financial.index'))->assertOk();
        $this->actingAs($finance, 'admin')->put(route('admin.settings.update'), [])->assertForbidden();
    }
}
