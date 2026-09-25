<?php

namespace Tests\Feature\Admin;

use App\Enums\ExpenseCategory;
use App\Enums\WithdrawalMethodType;
use App\Models\Admin;
use App\Models\Expense;
use App\Models\IncomeTransaction;
use App\Models\Member;
use App\Models\Sale;
use App\Services\MatchingService;
use App\Services\RefundService;
use App\Services\WithdrawalService;
use Database\Seeders\DemoNetworkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Seeded demo network at a fixed date, so every figure is known:
 *  - 20 members, activated 2026-08-31 … 2026-09-19 (19 in September);
 *  - 20 sales on 2026-09-20: 5 each of ৳1,000/5,000/10,000/25,000 = ৳205,000;
 *    cost of goods 40% = ৳82,000;
 *  - referral: 5% of the 19 non-root sales (৳204,000) = ৳10,200;
 *    binary on 2026-09-20 = ৳15,400 (9 members, root capped at ৳5,000);
 *  - plus, entered below: ৳1,000 other income, ৳5,000 marketing expense,
 *    ৳9,999.99 "product cost" expense (excluded) and an August expense.
 */
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-09-20 12:00:00');
        $this->seed(DemoNetworkSeeder::class);
        app(MatchingService::class)->runCycle(Carbon::parse('2026-09-20'));

        IncomeTransaction::factory()->create(['source' => 'other', 'amount' => 100_000, 'date' => '2026-09-10']);
        Expense::factory()->create(['category' => ExpenseCategory::Marketing, 'amount' => 500_000, 'date' => '2026-09-15']);
        Expense::factory()->create(['category' => ExpenseCategory::ProductCost, 'amount' => 999_999, 'date' => '2026-09-15']);
        Expense::factory()->create(['category' => ExpenseCategory::Salary, 'amount' => 300_000, 'date' => '2026-08-15']);

        $root = Member::query()->where('member_code', 'MBR-100001')->firstOrFail();
        app(WithdrawalService::class)->request($root, 150_000, WithdrawalMethodType::MobileBanking, ['provider' => 'bkash', 'mobile_number' => '+8801712345678']);

        $this->admin = Admin::factory()->superAdmin()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<string, int>
     */
    private function metrics(string $period = 'month'): array
    {
        return $this->actingAs($this->admin, 'admin')
            ->get(route('admin.dashboard', ['period' => $period]))
            ->assertOk()
            ->viewData('m');
    }

    public function test_this_month_figures_match_the_known_data()
    {
        $m = $this->metrics('month');

        $this->assertSame(20, $m['members_total']);
        $this->assertSame(20, $m['members_active']);
        $this->assertSame(19, $m['members_new']);

        $this->assertSame(20_500_000, $m['sales_amount']);
        $this->assertSame(20, $m['sales_count']);
        $this->assertSame(20_500_000, $m['sales_today_amount']);

        $this->assertSame(1_020_000 + 1_540_000, $m['commission_paid']);
        $this->assertSame(150_000, $m['withdrawals_open_amount']);
        $this->assertSame(1, $m['withdrawals_open_count']);

        $this->assertSame(20_600_000, $m['revenue']);            // sales + other income
        $this->assertSame(8_200_000, $m['cost_of_goods']);
        $this->assertSame(500_000, $m['expenses']);              // product_cost + August salary excluded
        $this->assertSame(12_400_000, $m['gross_profit']);
        $this->assertSame(9_340_000, $m['net_profit']);          // 124,000 − 25,600 − 5,000
    }

    public function test_the_dashboard_renders_the_numbers()
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('৳205,000.00')
            ->assertSee('৳93,400.00')
            ->assertSee('৳1,500.00');
    }

    public function test_periods_change_the_period_figures_only()
    {
        $today = $this->metrics('today');
        $this->assertSame(0, $today['members_new']);          // nobody activated on the 20th
        $this->assertSame(20, $today['members_total']);
        $this->assertSame(0, $today['expenses']);
        $this->assertSame(20_500_000, $today['revenue']);

        $all = $this->metrics('all');
        $this->assertSame(20, $all['members_new']);
        $this->assertSame(800_000, $all['expenses']);            // August salary now included

        $this->assertSame($this->metrics('month'), $this->metrics('nonsense'), 'Unknown periods fall back to this month');
    }

    public function test_refunded_sales_leave_revenue_and_cost_of_goods()
    {
        $sale = Sale::query()->whereHas('package', fn ($q) => $q->where('name', 'Business'))->firstOrFail();
        app(RefundService::class)->refund($sale, 'test', $this->admin);

        $m = $this->metrics('month');

        $this->assertSame(20_500_000 - 2_500_000, $m['sales_amount']);
        $this->assertSame(8_200_000 - 1_000_000, $m['cost_of_goods']);
        $this->assertSame(2_500_000, $m['refunded_amount']);
    }
}
