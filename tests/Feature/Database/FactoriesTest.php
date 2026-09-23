<?php

namespace Tests\Feature\Database;

use App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FactoriesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string<Model>}>
     */
    public static function models(): array
    {
        $models = [
            Models\Admin::class, Models\User::class, Models\Member::class, Models\BinaryNode::class,
            Models\Package::class, Models\Product::class, Models\Order::class, Models\OrderItem::class,
            Models\Sale::class, Models\Refund::class, Models\Wallet::class, Models\WalletTransaction::class,
            Models\Commission::class, Models\CommissionRule::class, Models\CommissionCycle::class,
            Models\TeamVolume::class, Models\Withdrawal::class, Models\WithdrawalMethod::class,
            Models\Payment::class, Models\KycDocument::class, Models\Rank::class,
            Models\RankAchievement::class, Models\Bonus::class, Models\Expense::class,
            Models\IncomeTransaction::class, Models\Setting::class,
        ];

        return collect($models)->mapWithKeys(fn ($m) => [class_basename($m) => [$m]])->all();
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[DataProvider('models')]
    public function test_factory_creates_a_persisted_model(string $model)
    {
        $instance = $model::factory()->create();

        $this->assertTrue($instance->exists);
        $this->assertModelExists($instance);
    }

    public function test_sale_factory_derives_member_and_bv_from_its_order()
    {
        $sale = Models\Sale::factory()->create();

        $this->assertSame($sale->order->member_id, $sale->member_id);
        $this->assertSame($sale->order->amount, $sale->amount);
        $this->assertSame($sale->package->bv_value, $sale->bv_value);
    }

    public function test_member_relationships_resolve()
    {
        $sponsor = Models\Member::factory()->active()->create();
        $member = Models\Member::factory()->active()->create([
            'sponsor_id' => $sponsor->id,
            'placement_parent_id' => $sponsor->id,
            'placement_side' => 'left',
        ]);

        $this->assertTrue($member->sponsor->is($sponsor));
        $this->assertTrue($member->placementParent->is($sponsor));
        $this->assertTrue($sponsor->sponsoredMembers->first()->is($member));
        $this->assertTrue($sponsor->placementChildren->first()->is($member));
        $this->assertTrue($member->user->member->is($member));
    }
}
