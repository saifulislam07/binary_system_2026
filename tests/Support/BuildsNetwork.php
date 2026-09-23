<?php

namespace Tests\Support;

use App\Enums\OrderStatus;
use App\Enums\PlacementSide;
use App\Enums\SaleStatus;
use App\Events\SaleCompleted;
use App\Models\BinaryNode;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\Member;
use App\Models\Order;
use App\Models\Package;
use App\Models\Sale;
use App\Models\VolumeLot;
use App\Models\Wallet;
use App\Services\PlacementService;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Helpers for commission-engine tests. Amounts are given in whole taka / BV
 * and converted to the stored ×100 scale.
 */
trait BuildsNetwork
{
    protected function seedCommissionRules(array $overrides = []): void
    {
        $rules = [
            CommissionRule::BINARY_RATE_BPS => '1000',
            CommissionRule::REFERRAL_RATE_BPS => '500',
            CommissionRule::DAILY_CAP => '500000',
            CommissionRule::WEEKLY_CAP => '2000000',
            CommissionRule::MONTHLY_CAP => '5000000',
            CommissionRule::CARRY_FORWARD_ENABLED => '1',
            CommissionRule::CAP_OVERFLOW_BEHAVIOR => 'void',
            ...$overrides,
        ];

        foreach ($rules as $key => $value) {
            CommissionRule::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }
    }

    protected function rule(string $key, string|int $value): void
    {
        CommissionRule::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }

    protected function root(): Member
    {
        return app(PlacementService::class)->activateMember(Member::factory()->create());
    }

    protected function join(Member $sponsor, PlacementSide $side): Member
    {
        return app(PlacementService::class)->activateMember(
            Member::factory()->create(['sponsor_id' => $sponsor->id, 'preferred_side' => $side]),
        );
    }

    /**
     * Record a paid sale of `$taka` (BV = price) and run the same hooks a live payment does.
     */
    protected function sell(Member $member, int $taka, bool $qualifying = true): Sale
    {
        $package = Package::factory()->create([
            'price' => $taka * 100,
            'bv_value' => $taka * 100,
            'is_qualifying' => $qualifying,
        ]);

        return DB::transaction(function () use ($member, $package) {
            $order = Order::query()->create([
                'order_number' => 'ORD-'.Str::upper(Str::random(12)),
                'member_id' => $member->id,
                'package_id' => $package->id,
                'amount' => $package->price,
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
            ]);

            $sale = $order->sale()->create([
                'member_id' => $member->id,
                'package_id' => $package->id,
                'amount' => $package->price,
                'bv_value' => $package->bv_value,
                'status' => SaleStatus::Completed,
            ]);

            SaleCompleted::dispatch($sale);

            return $sale;
        });
    }

    protected function node(Member $member): BinaryNode
    {
        return BinaryNode::query()->where('member_id', $member->id)->firstOrFail();
    }

    protected function balance(Member $member): int
    {
        return Wallet::query()->where('member_id', $member->id)->value('balance') ?? 0;
    }

    /**
     * Everything a refund must restore: tree volumes, deferred commission,
     * wallet balances and each member's net commission.
     *
     * @return array<string, mixed>
     */
    protected function moneyAndVolumeState(): array
    {
        return [
            'nodes' => BinaryNode::query()->orderBy('member_id')->get()->mapWithKeys(fn (BinaryNode $n) => [$n->member_id => [
                'left' => $n->left_volume,
                'right' => $n->right_volume,
                'left_lifetime' => $n->left_lifetime_volume,
                'right_lifetime' => $n->right_lifetime_volume,
                'deferred' => $n->deferred_commission,
            ]])->all(),
            'wallets' => Wallet::query()->orderBy('member_id')->pluck('balance', 'member_id')->all(),
            'net_commission' => Commission::query()
                ->whereIn('status', ['paid', 'reversed'])
                ->groupBy('member_id')
                ->orderBy('member_id')
                ->selectRaw('member_id, SUM(amount) AS net')
                ->pluck('net', 'member_id')
                ->map(fn ($v) => (int) $v)
                ->filter()
                ->all(),
        ];
    }

    /**
     * Node volume must always equal the sum of its open lots, and the cached
     * wallet balance must equal the ledger.
     */
    protected function assertLedgersConsistent(): void
    {
        foreach (BinaryNode::all() as $node) {
            foreach (PlacementSide::cases() as $side) {
                $open = (int) VolumeLot::query()->where('member_id', $node->member_id)->where('side', $side)->sum('remaining');
                $this->assertSame($open, $node->{$side->volumeColumn()}, "Member {$node->member_id} {$side->value} volume ≠ open lots");
            }
        }

        foreach (Wallet::all() as $wallet) {
            $this->assertSame(app(WalletService::class)->ledgerBalance($wallet), $wallet->balance, "Wallet {$wallet->id} cache ≠ ledger");
        }

        $this->assertSame(0, Order::query()->where('status', OrderStatus::Pending)->whereHas('sale')->count());
    }
}
