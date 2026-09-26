<?php

namespace Tests\Feature\Payments;

use App\DTOs\Payments\PaymentInitiation;
use App\DTOs\Payments\PaymentResult;
use App\Enums\MemberStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Events\SaleCompleted;
use App\Models\Member;
use App\Models\Order;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Sale;
use App\Payments\Contracts\PaymentGateway as GatewayContract;
use App\Payments\Gateways\BkashGateway;
use App\Services\PlacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * End-to-end checkout through the controllers, using the simulator gateway
 * (its callback is trusted only in non-production; real gateways verify
 * server-to-server and are tested with Http::fake in their own tests).
 */
#[Group('rule-2')]
#[Group('rule-4')]
class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    private Member $sponsor;

    private Member $pending;

    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        config(['payments.simulator' => true]);

        $this->package = Package::factory()->create(['name' => 'Premium', 'price' => 1_000_000, 'bv_value' => 1_000_000]);
        $this->sponsor = app(PlacementService::class)->activateMember(Member::factory()->create());
        $this->pending = Member::factory()->create(['sponsor_id' => $this->sponsor->id, 'package_id' => null]);
    }

    private function checkout(Member $member, string $gateway = 'simulator'): Order
    {
        $this->actingAs($member->user)
            ->post(route('checkout.store'), ['package_id' => $this->package->id, 'gateway' => $gateway])
            ->assertRedirect();

        return Order::query()->where('member_id', $member->id)->latest('id')->firstOrFail();
    }

    private function simulate(Order $order, string $status): TestResponse
    {
        $ref = $order->payments()->latest('id')->firstOrFail()->gateway_ref;

        return $this->get(route('payments.callback', ['gateway' => 'simulator', 'ref' => $ref, 'status' => $status]));
    }

    public function test_checkout_page_lists_active_packages_and_gateways()
    {
        $this->actingAs($this->pending->user)
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('checkout/Index')
                ->where('memberStatus', 'pending')
                ->where('packages', fn ($packages) => collect($packages)->pluck('name')->contains('Premium'))
                ->where('gateways', fn ($gateways) => collect($gateways)->pluck('value')->contains('simulator')));
    }

    public function test_checkout_creates_a_pending_order_and_sends_the_member_to_the_gateway()
    {
        $response = $this->actingAs($this->pending->user)
            ->post(route('checkout.store'), ['package_id' => $this->package->id, 'gateway' => 'simulator']);

        $order = Order::query()->where('member_id', $this->pending->id)->firstOrFail();
        $payment = $order->payments()->firstOrFail();

        $response->assertRedirect(route('payments.simulator.show', ['ref' => $payment->gateway_ref]));
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame(1_000_000, $order->amount);
        $this->assertSame(PaymentStatus::Initiated, $payment->status);
        $this->assertSame(1, $order->items()->count());

        $this->get(route('payments.simulator.show', ['ref' => $payment->gateway_ref]))->assertOk()->assertSee($order->order_number);
    }

    public function test_successful_payment_activates_the_member_and_creates_a_sale_with_the_package_bv()
    {
        Event::fake([SaleCompleted::class]);
        $order = $this->checkout($this->pending);

        $this->simulate($order, 'success')->assertRedirect(route('orders.show', $order));

        $order->refresh();
        $member = $this->pending->fresh();
        $sale = Sale::query()->where('order_id', $order->id)->firstOrFail();

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame(PaymentStatus::Success, $order->payments()->firstOrFail()->status);

        $this->assertSame(MemberStatus::Active, $member?->status);
        $this->assertNotNull($member->member_code);
        $this->assertSame($this->sponsor->id, $member->placement_parent_id);
        $this->assertSame($this->package->id, $member->package_id);

        $this->assertSame(SaleStatus::Completed, $sale->status);
        $this->assertSame($member->id, $sale->member_id);
        $this->assertSame(1_000_000, $sale->amount);
        $this->assertSame(1_000_000, $sale->bv_value);

        Event::assertDispatched(SaleCompleted::class, fn (SaleCompleted $e) => $e->sale->is($sale));

        $this->actingAs($member->user)->get(route('orders.show', $order))
            ->assertInertia(fn (Assert $page) => $page
                ->component('orders/Show')
                ->where('order.status', 'paid')
                ->where('order.memberCode', $member->member_code));
    }

    public function test_duplicate_success_callbacks_do_not_create_a_second_sale()
    {
        $order = $this->checkout($this->pending);

        $this->simulate($order, 'success');
        $code = $this->pending->fresh()?->member_code;
        $this->simulate($order, 'success');

        $this->assertSame(1, Sale::query()->where('order_id', $order->id)->count());
        $this->assertSame($code, $this->pending->fresh()?->member_code);
    }

    public function test_failed_payment_leaves_the_member_pending_and_creates_no_sale()
    {
        $order = $this->checkout($this->pending);

        $this->simulate($order, 'failed')->assertRedirect(route('orders.show', $order));

        $this->assertSame(OrderStatus::Failed, $order->fresh()?->status);
        $this->assertSame(PaymentStatus::Failed, $order->payments()->firstOrFail()->status);
        $this->assertSame(MemberStatus::Pending, $this->pending->fresh()?->status);
        $this->assertNull($this->pending->fresh()?->member_code);
        $this->assertSame(0, Sale::query()->count());
    }

    public function test_cancelled_payment_cancels_the_order()
    {
        $order = $this->checkout($this->pending);

        $this->simulate($order, 'cancelled');

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()?->status);
        $this->assertSame(MemberStatus::Pending, $this->pending->fresh()?->status);
    }

    public function test_a_late_success_after_a_failure_is_honoured()
    {
        $order = $this->checkout($this->pending);

        $this->simulate($order, 'failed');
        $this->simulate($order, 'success');

        $this->assertSame(OrderStatus::Paid, $order->fresh()?->status);
        $this->assertSame(MemberStatus::Active, $this->pending->fresh()?->status);
    }

    public function test_a_late_failure_after_success_changes_nothing()
    {
        $order = $this->checkout($this->pending);

        $this->simulate($order, 'success');
        $this->simulate($order, 'failed');

        $this->assertSame(OrderStatus::Paid, $order->fresh()?->status);
        $this->assertSame(PaymentStatus::Success, $order->payments()->firstOrFail()->status);
    }

    public function test_amount_mismatch_does_not_activate_and_is_flagged_for_review()
    {
        $this->instance(BkashGateway::class, new class implements GatewayContract
        {
            public function name(): PaymentGateway
            {
                return PaymentGateway::Bkash;
            }

            public function initiate(Order $order): PaymentInitiation
            {
                return new PaymentInitiation('https://pay.example/'.$order->order_number, 'BK-'.$order->order_number);
            }

            public function handleCallback(Request $request): PaymentResult
            {
                // Gateway says it captured only ৳1 for a ৳10,000 order.
                return new PaymentResult(PaymentStatus::Success, (string) $request->query('paymentID'), null, amount: 100);
            }
        });

        $order = $this->checkout($this->pending, 'bkash');
        $this->get(route('payments.callback', ['gateway' => 'bkash', 'paymentID' => 'BK-'.$order->order_number]));

        $this->assertSame(OrderStatus::Pending, $order->fresh()?->status);
        $this->assertSame(PaymentStatus::Failed, $order->payments()->firstOrFail()->status);
        $this->assertSame(MemberStatus::Pending, $this->pending->fresh()?->status);
        $this->assertSame(0, Sale::query()->count());
        $this->assertTrue(Activity::query()->where('description', 'like', 'Payment amount mismatch%')->exists());
    }

    public function test_active_members_can_buy_again_without_changing_their_code()
    {
        $order = $this->checkout($this->sponsor);
        $this->simulate($order, 'success');

        $this->assertSame(OrderStatus::Paid, $order->fresh()?->status);
        $this->assertSame($this->sponsor->member_code, $this->sponsor->fresh()?->member_code);
        $this->assertSame(1, Sale::query()->where('member_id', $this->sponsor->id)->count());
    }

    public function test_suspended_members_cannot_check_out()
    {
        $suspended = Member::factory()->suspended()->create();

        $this->actingAs($suspended->user)->get(route('checkout.index'))->assertForbidden();
        $this->actingAs($suspended->user)
            ->post(route('checkout.store'), ['package_id' => $this->package->id, 'gateway' => 'simulator'])
            ->assertForbidden();
    }

    public function test_members_cannot_view_other_members_orders()
    {
        $order = $this->checkout($this->pending);

        $this->actingAs($this->sponsor->user)->get(route('orders.show', $order))->assertForbidden();
    }

    public function test_unknown_gateway_and_unknown_payment_are_rejected()
    {
        $this->get(route('payments.callback', ['gateway' => 'paypal']))->assertNotFound();

        $this->get(route('payments.callback', ['gateway' => 'simulator', 'ref' => 'SIM-NOPE', 'status' => 'success']))
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHasErrors('gateway');

        $this->assertSame(0, Payment::query()->where('status', PaymentStatus::Success)->count());
    }

    public function test_simulator_is_unavailable_when_disabled()
    {
        config(['payments.simulator' => false]);

        $this->actingAs($this->pending->user)
            ->post(route('checkout.store'), ['package_id' => $this->package->id, 'gateway' => 'simulator'])
            ->assertSessionHasErrors('gateway');

        $this->get(route('payments.simulator.show', ['ref' => 'SIM-X']))->assertNotFound();
    }
}
