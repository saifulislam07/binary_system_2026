<?php

namespace Tests\Feature\Database;

use App\Enums\MemberStatus;
use App\Enums\PlacementSide;
use App\Enums\SaleStatus;
use App\Enums\TransactionDirection;
use App\Models\BinaryNode;
use App\Models\CommissionRule;
use App\Models\Member;
use App\Models\Package;
use App\Models\Rank;
use App\Models\Setting;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoNetworkSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class SeededNetworkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase seeds once per process (roles only); seed the full
        // dataset inside this test's transaction so it is rolled back after.
        $this->seed(DatabaseSeeder::class);
    }

    public function test_seeds_twenty_active_members_with_sequential_codes()
    {
        $members = Member::query()->orderBy('id')->get();

        $this->assertCount(DemoNetworkSeeder::MEMBER_COUNT, $members);
        $this->assertTrue($members->every(fn (Member $m) => $m->status === MemberStatus::Active));
        $this->assertSame(
            collect(range(0, DemoNetworkSeeder::MEMBER_COUNT - 1))->map(fn ($i) => 'MBR-'.(DemoNetworkSeeder::FIRST_CODE + $i))->all(),
            $members->pluck('member_code')->all(),
        );
    }

    public function test_every_member_has_a_wallet_and_a_binary_node()
    {
        foreach (Member::query()->with(['wallet', 'binaryNode'])->get() as $member) {
            $this->assertInstanceOf(Wallet::class, $member->wallet, "{$member->member_code} has no wallet");
            $this->assertInstanceOf(BinaryNode::class, $member->binaryNode, "{$member->member_code} has no binary node");
        }
    }

    public function test_tree_has_a_single_root_owned_by_the_test_member()
    {
        $roots = Member::query()->whereNull('placement_parent_id')->get();

        $this->assertCount(1, $roots);
        $this->assertSame(DemoNetworkSeeder::ROOT_EMAIL, $roots->first()->user->email);
        $this->assertNull($roots->first()->placement_side);
        $this->assertNull($roots->first()->sponsor_id);
    }

    public function test_placement_columns_and_binary_nodes_agree_in_both_directions()
    {
        $members = Member::query()->with('binaryNode')->get()->keyBy('id');

        // members.placement_* → parent's binary_nodes child slot
        foreach ($members as $member) {
            if ($member->placement_parent_id === null) {
                continue;
            }

            $parentNode = $members[$member->placement_parent_id]->binaryNode;
            $this->assertNotNull($member->placement_side);
            $this->assertSame($member->id, $parentNode->childId($member->placement_side), "{$member->member_code} is not in its parent's slot");
        }

        // binary_nodes child slot → child's members.placement_*
        foreach ($members as $member) {
            foreach (PlacementSide::cases() as $side) {
                $childId = $member->binaryNode->childId($side);

                if ($childId === null) {
                    continue;
                }

                $this->assertSame($member->id, $members[$childId]->placement_parent_id);
                $this->assertSame($side, $members[$childId]->placement_side);
            }
        }
    }

    public function test_seeded_tree_matches_the_documented_shape()
    {
        $code = fn (?int $id) => $id ? Member::query()->findOrFail($id)->member_code : null;
        $node = fn (string $memberCode) => Member::query()->where('member_code', $memberCode)->firstOrFail()->binaryNode;

        $this->assertSame('MBR-100002', $code($node('MBR-100001')->left_child_id));
        $this->assertSame('MBR-100003', $code($node('MBR-100001')->right_child_id));
        $this->assertSame('MBR-100008', $code($node('MBR-100004')->left_child_id));
        $this->assertSame('MBR-100020', $code($node('MBR-100010')->left_child_id));
        $this->assertNull($node('MBR-100010')->right_child_id);
    }

    public function test_spillover_members_have_a_sponsor_different_from_their_placement_parent()
    {
        $spillover = Member::query()
            ->whereNotNull('placement_parent_id')
            ->whereColumn('sponsor_id', '!=', 'placement_parent_id')
            ->pluck('member_code')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['MBR-100004', 'MBR-100005', 'MBR-100006', 'MBR-100007'], $spillover);
    }

    public function test_node_volumes_equal_the_bv_of_each_subtree()
    {
        $members = Member::query()->with('binaryNode')->get()->keyBy('id');
        $bvByMember = Member::query()->withSum(['sales' => fn ($q) => $q->where('status', SaleStatus::Completed)], 'bv_value')
            ->pluck('sales_sum_bv_value', 'id');

        $subtreeBv = function (?int $memberId) use (&$subtreeBv, $members, $bvByMember): int {
            if ($memberId === null) {
                return 0;
            }
            $node = $members[$memberId]->binaryNode;

            return (int) $bvByMember[$memberId] + $subtreeBv($node->left_child_id) + $subtreeBv($node->right_child_id);
        };

        foreach ($members as $member) {
            $node = $member->binaryNode;
            $this->assertSame($subtreeBv($node->left_child_id), $node->left_lifetime_volume, "{$member->member_code} left");
            $this->assertSame($subtreeBv($node->right_child_id), $node->right_lifetime_volume, "{$member->member_code} right");
            // No commission cycle has run yet, so nothing has been matched away.
            $this->assertSame($node->left_lifetime_volume, $node->left_volume);
            $this->assertSame($node->right_lifetime_volume, $node->right_volume);
        }
    }

    public function test_every_member_has_one_paid_sale_matching_their_package()
    {
        foreach (Member::query()->with(['sales.order', 'package'])->get() as $member) {
            $this->assertCount(1, $member->sales);
            $sale = $member->sales->first();
            $this->assertSame($member->package_id, $sale->package_id);
            $this->assertSame($member->package->price, $sale->amount);
            $this->assertSame($member->package->bv_value, $sale->bv_value);
            $this->assertSame($sale->amount, $sale->order->amount);
        }
    }

    public function test_wallet_balances_match_their_ledgers()
    {
        foreach (Wallet::query()->with('transactions')->get() as $wallet) {
            /** @var Collection<int, WalletTransaction> $ledger */
            $ledger = $wallet->transactions;
            $expected = $ledger->sum(fn ($t) => $t->direction === TransactionDirection::Credit ? $t->amount : -$t->amount);

            $this->assertSame($expected, $wallet->balance);
        }
    }

    public function test_reference_data_is_seeded()
    {
        $this->assertSame(
            ['Basic' => 100_000, 'Standard' => 500_000, 'Premium' => 1_000_000, 'Business' => 2_500_000],
            Package::query()->orderBy('sort_order')->pluck('price', 'name')->all(),
        );
        $this->assertSame(1000, CommissionRule::int(CommissionRule::BINARY_RATE_BPS));
        $this->assertSame(500, CommissionRule::int(CommissionRule::REFERRAL_RATE_BPS));
        $this->assertSame(500_000, CommissionRule::int(CommissionRule::DAILY_CAP));
        $this->assertSame(2_000_000, CommissionRule::int(CommissionRule::WEEKLY_CAP));
        $this->assertSame(5_000_000, CommissionRule::int(CommissionRule::MONTHLY_CAP));
        $this->assertTrue(CommissionRule::bool(CommissionRule::CARRY_FORWARD_ENABLED));
        $this->assertSame('void', CommissionRule::raw(CommissionRule::CAP_OVERFLOW_BEHAVIOR));
        $this->assertSame(
            ['Member', 'Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond'],
            Rank::query()->orderBy('sort_order')->pluck('name')->all(),
        );
        $this->assertSame(100_000, Setting::int(Setting::MIN_WITHDRAWAL));
    }

    public function test_reference_seeder_is_idempotent_and_keeps_admin_changes()
    {
        CommissionRule::query()->where('key', CommissionRule::BINARY_RATE_BPS)->update(['value' => '1200']);

        $this->seed(ReferenceDataSeeder::class);

        $this->assertSame(4, Package::query()->count());
        $this->assertSame(6, Rank::query()->count());
        $this->assertSame(1200, CommissionRule::int(CommissionRule::BINARY_RATE_BPS));
    }
}
