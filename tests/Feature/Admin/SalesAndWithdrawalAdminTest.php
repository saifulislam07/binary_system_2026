<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderStatus;
use App\Enums\PlacementSide;
use App\Enums\SaleStatus;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalMethodType;
use App\Enums\WithdrawalStatus;
use App\Models\Admin;
use App\Models\Member;
use App\Models\Order;
use App\Models\Withdrawal;
use App\Services\MatchingService;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsNetwork;
use Tests\TestCase;

#[Group('rule-9')]
#[Group('rule-10')]
class SalesAndWithdrawalAdminTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Admin $admin;

    private Member $root;

    private Member $left;

    private Member $right;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCommissionRules();
        $this->admin = Admin::factory()->superAdmin()->create();
        $this->root = $this->root();
        $this->left = $this->join($this->root, PlacementSide::Left);
        $this->right = $this->join($this->root, PlacementSide::Right);
    }

    public function test_sales_list_totals_orders_by_status()
    {
        $this->sell($this->left, 1_000);
        $this->sell($this->right, 5_000);
        Order::factory()->create(['member_id' => $this->left->id, 'amount' => 250_000]); // pending

        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.sales.index'))->assertOk();

        $totals = $response->viewData('totals');
        $this->assertSame(['orders' => 2, 'amount' => 600_000], $totals['paid']);
        $this->assertSame(['orders' => 1, 'amount' => 250_000], $totals['pending']);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.sales.index', ['member' => $this->right->member_code]))
            ->assertViewHas('orders', fn ($orders) => $orders->total() === 1);
    }

    public function test_sale_detail_shows_bv_flow_and_commissions()
    {
        $sale = $this->sell($this->left, 1_000);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.sales.show', $sale))
            ->assertOk()
            ->assertSee('BV flow up the placement tree')
            ->assertSee($this->root->member_code)
            ->assertSee('Waiting to be matched')
            ->assertViewHas('commissions', fn ($commissions) => $commissions->count() === 1); // referral to root
    }

    public function test_refund_from_the_sales_screen_triggers_the_phase_5_reversal()
    {
        $this->sell($this->right, 1_000);
        $before = $this->moneyAndVolumeState();

        $sale = $this->sell($this->left, 1_000);
        app(MatchingService::class)->runCycle(Carbon::parse('2026-09-20'));
        $this->assertSame(10_000 + 10_000, $this->balance($this->root), 'two referrals (৳50 each) + ৳100 binary');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.sales.refund', $sale), ['reason' => 'Customer returned the product'])
            ->assertRedirect(route('admin.sales.show', $sale))
            ->assertSessionHas('success');

        $sale->refresh();
        $this->assertSame(SaleStatus::Refunded, $sale->status);
        $this->assertNotNull($sale->reversed_at, 'The reversal job ran');
        $this->assertSame($this->admin->id, $sale->refund->processed_by);
        $this->assertEquals($before, $this->moneyAndVolumeState());
        $this->assertLedgersConsistent();

        // A second refund is refused.
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.sales.refund', $sale), ['reason' => 'Again'])
            ->assertSessionHas('error');
    }

    public function test_withdrawal_queue_runs_the_full_state_machine()
    {
        app(WalletService::class)->credit($this->left, 500_000, WalletTransactionType::Adjustment);
        $withdrawal = app(WithdrawalService::class)->request($this->left, 200_000, WithdrawalMethodType::MobileBanking, ['provider' => 'bkash', 'mobile_number' => '+8801712345678']);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.withdrawals.index'))
            ->assertOk()
            ->assertSee('+8801712345678') // admins see the full account to pay it
            ->assertViewHas('withdrawals', fn ($w) => $w->total() === 1);

        $this->actingAs($this->admin, 'admin')->post(route('admin.withdrawals.approve', $withdrawal))->assertSessionHas('success');
        $this->actingAs($this->admin, 'admin')->post(route('admin.withdrawals.processing', $withdrawal))->assertSessionHas('success');
        $this->actingAs($this->admin, 'admin')->post(route('admin.withdrawals.paid', $withdrawal), ['payout_reference' => ''])->assertSessionHasErrors('payout_reference');
        $this->actingAs($this->admin, 'admin')->post(route('admin.withdrawals.paid', $withdrawal), ['payout_reference' => 'TRX123'])->assertSessionHas('success');

        $this->assertSame(WithdrawalStatus::Paid, $withdrawal->fresh()?->status);
        $this->assertSame('TRX123', $withdrawal->fresh()?->payout_reference);
        $this->assertSame(300_000, $this->balance($this->left));

        // Invalid transition from the queue is refused with a message, not an error page.
        $this->actingAs($this->admin, 'admin')->post(route('admin.withdrawals.approve', $withdrawal))->assertSessionHas('error');
    }

    public function test_rejecting_from_the_queue_returns_the_money()
    {
        app(WalletService::class)->credit($this->left, 500_000, WalletTransactionType::Adjustment);
        $withdrawal = app(WithdrawalService::class)->request($this->left, 200_000, WithdrawalMethodType::MobileBanking, ['provider' => 'nagad', 'mobile_number' => '+8801812345678']);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.withdrawals.reject', $withdrawal), ['reason' => 'Number not registered with Nagad'])
            ->assertSessionHas('success');

        $this->assertSame(WithdrawalStatus::Rejected, $withdrawal->fresh()?->status);
        $this->assertSame(500_000, $this->balance($this->left));
    }

    public function test_support_admins_cannot_touch_sales_or_withdrawals()
    {
        $support = Admin::factory()->create()->assignRole('support');
        $sale = $this->sell($this->left, 1_000);
        $withdrawal = Withdrawal::factory()->create(['member_id' => $this->left->id]);

        $this->actingAs($support, 'admin')->post(route('admin.sales.refund', $sale), ['reason' => 'nope'])->assertForbidden();
        $this->actingAs($support, 'admin')->post(route('admin.withdrawals.approve', $withdrawal))->assertForbidden();
        $this->assertSame(OrderStatus::Paid, $sale->order->fresh()?->status);
    }
}
