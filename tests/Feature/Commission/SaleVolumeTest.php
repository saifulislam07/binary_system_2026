<?php

namespace Tests\Feature\Commission;

use App\Models\Member;
use App\Models\Order;
use App\Models\Package;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsNetwork;
use Tests\TestCase;

/**
 * Rule #4: packages are data, not code, and what flows up the tree is the
 * package's BV — never the cash amount. Rule #7: the referral bonus is a
 * share of the cash amount.
 */
#[Group('rule-4')]
#[Group('rule-7')]
class SaleVolumeTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    public function test_an_edited_package_price_is_charged_and_its_bv_not_its_price_flows_up_the_tree()
    {
        $this->seedCommissionRules();
        config(['payments.simulator' => true]);

        // Admin edits the Premium package: ৳12,500 but only 6,000 BV.
        $package = Package::query()->where('name', 'Premium')->firstOrFail();
        $package->update(['price' => 1_250_000, 'bv_value' => 600_000, 'is_qualifying' => true]);

        $sponsor = $this->root();
        $buyer = Member::factory()->create(['sponsor_id' => $sponsor->id, 'package_id' => $package->id]);

        $this->actingAs($buyer->user)->post(route('checkout.store'), ['package_id' => $package->id, 'gateway' => 'simulator']);
        $order = Order::query()->where('member_id', $buyer->id)->firstOrFail();
        $this->assertSame(1_250_000, $order->amount, 'charged the price stored in the packages table');

        $this->get(route('payments.callback', ['gateway' => 'simulator', 'ref' => $order->payments()->firstOrFail()->gateway_ref, 'status' => 'success']));

        $sale = Sale::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(1_250_000, $sale->amount);
        $this->assertSame(600_000, $sale->bv_value);

        $node = $this->node($sponsor);
        $this->assertSame(600_000, $node->left_volume + $node->right_volume, 'BV, not cash, accrues to the upline');

        // Referral: 5% of the ৳12,500 cash amount = ৳625.
        $this->assertSame(62_500, $this->balance($sponsor));
        $this->assertLedgersConsistent();
    }

    public function test_a_price_change_does_not_touch_sales_already_made()
    {
        $this->seedCommissionRules();
        $root = $this->root();
        $sale = $this->sell($root, 1_000);

        Package::query()->whereKey($sale->package_id)->update(['price' => 999_900, 'bv_value' => 999_900]);

        $sale->refresh();
        $this->assertSame(100_000, $sale->amount);
        $this->assertSame(100_000, $sale->bv_value);
    }
}
