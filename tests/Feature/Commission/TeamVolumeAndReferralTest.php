<?php

namespace Tests\Feature\Commission;

use App\Enums\CommissionType;
use App\Enums\MemberStatus;
use App\Enums\PayoutStatus;
use App\Enums\PlacementSide;
use App\Enums\WalletTransactionType;
use App\Models\Commission;
use App\Models\VolumeLot;
use App\Models\WalletTransaction;
use App\Services\ReferralBonusService;
use App\Services\TeamVolumeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsNetwork;
use Tests\TestCase;

class TeamVolumeAndReferralTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCommissionRules();
    }

    public function test_sale_bv_accrues_to_every_placement_ancestor_on_the_side_it_came_up_through()
    {
        $root = $this->root();
        $a = $this->join($root, PlacementSide::Left);
        $b = $this->join($a, PlacementSide::Right);   // root.L → a.R → b

        $this->sell($b, 1_000);

        $this->assertSame(0, $this->node($b)->left_volume + $this->node($b)->right_volume, 'Seller gets no volume from their own sale');
        $this->assertSame(100_000, $this->node($a)->right_volume);
        $this->assertSame(0, $this->node($a)->left_volume);
        $this->assertSame(100_000, $this->node($root)->left_volume);
        $this->assertSame(0, $this->node($root)->right_volume);
        $this->assertSame(100_000, $this->node($root)->left_lifetime_volume);

        $this->assertSame(2, VolumeLot::query()->count());
        $this->assertLedgersConsistent();
    }

    public function test_volume_follows_the_placement_tree_not_the_sponsor_chain()
    {
        $root = $this->root();
        $a = $this->join($root, PlacementSide::Left);
        $spill = $this->join($root, PlacementSide::Left); // sponsored by root, placed under a

        $this->assertSame($a->id, $spill->placement_parent_id);

        $this->sell($spill, 500);

        $this->assertSame(50_000, $this->node($a)->left_volume, 'Placement parent receives volume');
        $this->assertSame(50_000, $this->node($root)->left_volume);
    }

    public function test_accrual_is_idempotent_per_sale()
    {
        $root = $this->root();
        $a = $this->join($root, PlacementSide::Right);
        $sale = $this->sell($a, 1_000);

        app(TeamVolumeService::class)->accrueVolume($sale);

        $this->assertSame(100_000, $this->node($root)->right_volume);
        $this->assertSame(1, VolumeLot::query()->count());
    }

    public function test_referral_bonus_goes_to_the_sponsor_not_the_placement_parent()
    {
        $root = $this->root();
        $a = $this->join($root, PlacementSide::Left);
        $spill = $this->join($root, PlacementSide::Left); // placed under a, sponsored by root

        $before = $this->balance($a);
        $this->sell($spill, 10_000);

        $commission = Commission::query()->where('type', CommissionType::Referral)->where('source_sale_id', '!=', null)->latest('id')->firstOrFail();
        $this->assertSame($root->id, $commission->member_id);
        $this->assertSame(50_000, $commission->amount); // 5% of ৳10,000 = ৳500
        $this->assertSame(PayoutStatus::Paid, $commission->status);
        $this->assertSame($before, $this->balance($a), 'Placement parent gets nothing');

        $credit = WalletTransaction::query()->where('reference_type', $commission->getMorphClass())->where('reference_id', $commission->id)->firstOrFail();
        $this->assertSame(WalletTransactionType::ReferralBonus, $credit->type);
        $this->assertSame(50_000, $credit->amount);
        $this->assertLedgersConsistent();
    }

    public function test_referral_bonus_is_only_paid_on_qualifying_packages()
    {
        $root = $this->root();
        $a = $this->join($root, PlacementSide::Left);
        $rootBalance = $this->balance($root);

        $this->sell($a, 10_000, qualifying: false);

        $this->assertSame($rootBalance, $this->balance($root));
        $this->assertSame(0, Commission::query()->where('type', CommissionType::Referral)->count());
    }

    public function test_referral_bonus_is_paid_once_per_sale()
    {
        $root = $this->root();
        $a = $this->join($root, PlacementSide::Left);
        $sale = $this->sell($a, 1_000);

        app(ReferralBonusService::class)->payOnSale($sale);

        $this->assertSame(1, Commission::query()->where('type', CommissionType::Referral)->count());
        $this->assertSame(5_000, $this->balance($root));
    }

    public function test_suspended_sponsor_gets_a_voided_record_and_no_money()
    {
        $root = $this->root();
        $a = $this->join($root, PlacementSide::Left);
        $root->forceFill(['status' => MemberStatus::Suspended])->save();

        $this->sell($a, 1_000);

        $this->assertSame(PayoutStatus::Voided, Commission::query()->where('type', CommissionType::Referral)->firstOrFail()->status);
        $this->assertSame(0, $this->balance($root));
    }
}
