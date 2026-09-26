<?php

namespace Tests\Feature\Payments;

use App\Enums\OrderStatus;
use App\Enums\SaleStatus;
use App\Exceptions\RefundException;
use App\Jobs\ReverseCommissionForSale;
use App\Models\Admin;
use App\Models\Refund;
use App\Models\Sale;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

#[Group('rule-10')]
class RefundServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_refund_marks_the_sale_refunded_records_it_and_queues_the_reversal()
    {
        Queue::fake();
        $sale = Sale::factory()->create();
        $admin = Admin::factory()->create();

        $refund = app(RefundService::class)->refund($sale, 'Customer changed their mind', $admin);

        $sale->refresh();
        $this->assertSame(SaleStatus::Refunded, $sale->status);
        $this->assertNotNull($sale->refunded_at);
        $this->assertSame(OrderStatus::Refunded, $sale->order->status);

        $this->assertSame($sale->amount, $refund->amount);
        $this->assertSame($admin->id, $refund->processed_by);
        $this->assertSame('Customer changed their mind', $refund->reason);

        Queue::assertPushed(ReverseCommissionForSale::class, fn (ReverseCommissionForSale $job) => $job->saleId === $sale->id);
        $this->assertTrue(Activity::query()->where('description', 'Sale refunded')->where('subject_id', $sale->id)->exists());
    }

    public function test_a_sale_cannot_be_refunded_twice()
    {
        Queue::fake();
        $sale = Sale::factory()->create();
        $service = app(RefundService::class);

        $service->refund($sale, 'first');

        try {
            $service->refund($sale, 'second');
            $this->fail('Expected a RefundException.');
        } catch (RefundException) {
            // expected
        }

        $this->assertSame(1, Refund::query()->where('sale_id', $sale->id)->count());
        Queue::assertPushed(ReverseCommissionForSale::class, 1);
    }
}
