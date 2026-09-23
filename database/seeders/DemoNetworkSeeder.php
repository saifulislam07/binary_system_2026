<?php

namespace Database\Seeders;

use App\Enums\MemberStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\PlacementSide;
use App\Enums\SaleStatus;
use App\Models\BinaryNode;
use App\Models\Member;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use App\Services\PlacementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dev/test network: 20 active members in a complete binary tree, each with
 * a wallet and one paid package sale whose BV has been accrued up the
 * placement tree. Never run in production.
 *
 * Tree (level order, index → member code MBR-1000{index+1}):
 *
 *                          0
 *              1                       2
 *        3           4           5           6
 *     7     8     9    10    11    12    13    14
 *   15 16 17 18 19
 *
 * Sponsorship: the root personally sponsors 1–6 (3–6 are spillover: sponsored
 * by the root but placed under 1 or 2). Everyone else is sponsored by their
 * placement parent.
 */
class DemoNetworkSeeder extends Seeder
{
    public const MEMBER_COUNT = 20;

    public const FIRST_CODE = 100001;

    public const ROOT_EMAIL = 'test@example.com';

    public function run(): void
    {
        $placement = app(PlacementService::class);

        DB::transaction(function () use ($placement) {
            $packages = Package::query()->orderBy('sort_order')->get()->values();
            $startedAt = now()->subDays(self::MEMBER_COUNT);

            /** @var array<int, Member> $members */
            $members = [];

            for ($i = 0; $i < self::MEMBER_COUNT; $i++) {
                $package = $packages[$i % $packages->count()];
                $activatedAt = $startedAt->copy()->addDays($i);

                $user = User::factory()->create($i === 0
                    ? ['name' => 'Test Member', 'email' => self::ROOT_EMAIL]
                    : []);
                $user->assignRole('member');

                $sponsorIndex = self::sponsorIndex($i);

                $member = Member::query()->create([
                    'user_id' => $user->id,
                    'sponsor_id' => $sponsorIndex === null ? null : $members[$sponsorIndex]->id,
                    'preferred_side' => self::preferredSide($i),
                    'package_id' => $package->id,
                    'status' => MemberStatus::Pending,
                    'nid' => fake()->unique()->numerify('##########'),
                    'address' => fake()->address(),
                ]);

                // The real engine assigns the code and resolves spillover.
                $member = $placement->activateMember($member);
                $member->forceFill(['activated_at' => $activatedAt])->save();
                $members[$i] = $member;

                $this->recordPaidSale($member, $package, $activatedAt);
            }

            $this->accrueVolumes($members);
        });
    }

    /**
     * The root personally sponsors 1–6; everyone else is sponsored by their
     * (documented) placement parent.
     */
    private static function sponsorIndex(int $i): ?int
    {
        return match (true) {
            $i === 0 => null,
            $i <= 6 => 0,
            default => intdiv($i - 1, 2),
        };
    }

    /**
     * Sides chosen so BFS spillover reproduces the documented level-order
     * tree: 3 and 4 (preferring left) spill under 1, 5 and 6 (preferring
     * right) spill under 2; from 7 on the sponsor's direct slot is free.
     */
    private static function preferredSide(int $i): ?PlacementSide
    {
        return match (true) {
            $i === 0 => null,
            $i <= 6 => in_array($i, [1, 3, 4], true) ? PlacementSide::Left : PlacementSide::Right,
            default => $i % 2 === 1 ? PlacementSide::Left : PlacementSide::Right,
        };
    }

    private function recordPaidSale(Member $member, Package $package, \DateTimeInterface $paidAt): void
    {
        $order = Order::query()->create([
            'order_number' => 'ORD-'.Str::upper(Str::random(12)),
            'member_id' => $member->id,
            'package_id' => $package->id,
            'amount' => $package->price,
            'status' => OrderStatus::Paid,
            'paid_at' => $paidAt,
        ]);

        $order->items()->create([
            'package_id' => $package->id,
            'quantity' => 1,
            'unit_price' => $package->price,
            'total' => $package->price,
        ]);

        $order->payments()->create([
            'gateway' => PaymentGateway::Bkash,
            'gateway_ref' => 'DEMO-'.Str::upper(Str::random(16)),
            'amount' => $package->price,
            'status' => PaymentStatus::Success,
        ]);

        $order->sale()->create([
            'member_id' => $member->id,
            'package_id' => $package->id,
            'amount' => $package->price,
            'bv_value' => $package->bv_value,
            'status' => SaleStatus::Completed,
        ]);
    }

    /**
     * Add each sale's BV to every placement ancestor, on the side the walk
     * came up through. (Phase 5's TeamVolumeService does this for live sales.)
     *
     * @param  array<int, Member>  $members
     */
    private function accrueVolumes(array $members): void
    {
        $byId = collect($members)->keyBy('id');
        $totals = []; // member_id => ['left' => int, 'right' => int]

        foreach ($members as $member) {
            $bv = (int) $member->sales()->where('status', SaleStatus::Completed)->sum('bv_value');
            $current = $member;

            while ($current->placement_parent_id !== null && $current->placement_side !== null) {
                $side = $current->placement_side->value;
                $totals[$current->placement_parent_id][$side] = ($totals[$current->placement_parent_id][$side] ?? 0) + $bv;
                $current = $byId[$current->placement_parent_id];
            }
        }

        foreach ($totals as $memberId => $volume) {
            BinaryNode::query()->where('member_id', $memberId)->update([
                'left_volume' => $volume['left'] ?? 0,
                'right_volume' => $volume['right'] ?? 0,
                'left_lifetime_volume' => $volume['left'] ?? 0,
                'right_lifetime_volume' => $volume['right'] ?? 0,
            ]);
        }
    }
}
