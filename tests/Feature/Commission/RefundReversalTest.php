<?php

namespace Tests\Feature\Commission;

use App\Enums\CommissionType;
use App\Enums\PayoutStatus;
use App\Enums\PlacementSide;
use App\Enums\WalletTransactionType;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\Member;
use App\Models\Sale;
use App\Models\WalletTransaction;
use App\Services\CommissionReversalService;
use App\Services\MatchingService;
use App\Services\RefundService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\BuildsNetwork;
use Tests\TestCase;

/**
 * Rule #10: refunding a sale reverses its BV and every commission it
 * generated, via explicit reversal entries, so money and volume end up
 * exactly as if the sale had never happened.
 */
#[Group('rule-10')]
class RefundReversalTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Member $root;

    private Member $left;

    private Member $right;

    private Member $leftChild;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCommissionRules();
        $this->root = $this->root();
        $this->left = $this->join($this->root, PlacementSide::Left);
        $this->right = $this->join($this->root, PlacementSide::Right);
        $this->leftChild = $this->join($this->left, PlacementSide::Left);
    }

    private function runCycle(string $date = '2026-09-20'): void
    {
        app(MatchingService::class)->runCycle(Carbon::parse($date));
    }

    /**
     * Refund through the real path: RefundService queues the reversal job
     * (sync queue in tests), which runs CommissionReversalService.
     */
    private function refund(Sale $sale): void
    {
        app(RefundService::class)->refund($sale, 'test refund');
    }

    public function test_refund_before_matching_restores_the_pre_sale_state()
    {
        $this->sell($this->right, 1_000);
        $before = $this->moneyAndVolumeState();

        $sale = $this->sell($this->leftChild, 5_000); // volume to left and root, referral to left
        $this->assertNotEquals($before, $this->moneyAndVolumeState());

        $this->refund($sale);

        $this->assertEquals($before, $this->moneyAndVolumeState());
        $this->assertNotNull($sale->fresh()?->reversed_at);
        $this->assertLedgersConsistent();
    }

    public function test_refund_after_matching_exactly_cancels_volume_commission_and_wallet_entries()
    {
        $this->sell($this->right, 1_000);                 // root: right 1000 BV waiting
        $before = $this->moneyAndVolumeState();

        $sale = $this->sell($this->leftChild, 1_000);     // root: left 1000 → pairs with right
        $this->runCycle();                                 // root earns ৳100 binary + left earned ৳50 referral

        $this->assertSame(0, $this->node($this->root)->right_volume, 'Right side was consumed by matching');

        $this->refund($sale);

        $this->assertEquals($before, $this->moneyAndVolumeState(), 'Post-reversal state must equal pre-sale state');
        $this->assertSame(100_000, $this->node($this->root)->right_volume, 'Partner volume is given back');
        $this->assertLedgersConsistent();

        // Originals are untouched; reversals are separate negative rows pointing at them.
        $binary = Commission::query()->where('member_id', $this->root->id)->where('type', CommissionType::Binary)->where('status', PayoutStatus::Paid)->firstOrFail();
        $this->assertSame(10_000, $binary->amount);
        $reversal = Commission::query()->where('reverses_commission_id', $binary->id)->firstOrFail();
        $this->assertSame(-10_000, $reversal->amount);
        $this->assertSame(PayoutStatus::Reversed, $reversal->status);
        $this->assertSame($sale->id, $reversal->source_sale_id);

        $referralReversal = Commission::query()->where('member_id', $this->left->id)->where('type', CommissionType::Referral)->where('status', PayoutStatus::Reversed)->firstOrFail();
        $this->assertSame(-5_000, $referralReversal->amount);

        $this->assertSame(2, WalletTransaction::query()->where('type', WalletTransactionType::Reversal)->count());
    }

    public function test_refund_after_partial_matching_only_claws_back_the_matched_part()
    {
        $this->sell($this->right, 400);                    // right 400
        $sale = $this->sell($this->leftChild, 1_000);      // left 1000 → matched 400, 600 carried
        $this->runCycle();

        $rootBinaryBefore = (int) Commission::query()->where('member_id', $this->root->id)->where('type', CommissionType::Binary)->sum('amount');
        $this->assertSame(4_000, $rootBinaryBefore);

        $this->refund($sale);

        $node = $this->node($this->root);
        $this->assertSame(0, $node->left_volume, 'Carried 600 removed');
        $this->assertSame(40_000, $node->right_volume, 'Right 400 given back');
        $this->assertSame(0, (int) Commission::query()->where('member_id', $this->root->id)->where('type', CommissionType::Binary)->sum('amount'));
        $this->assertLedgersConsistent();
    }

    public function test_with_carry_forward_off_the_partner_volume_is_not_resurrected()
    {
        $this->rule(CommissionRule::CARRY_FORWARD_ENABLED, '0');
        $this->sell($this->right, 1_000);
        $sale = $this->sell($this->leftChild, 1_000);
        $this->runCycle();

        $this->refund($sale);

        // Without the sale, the right 1000 would have been flushed in that cycle anyway.
        $this->assertSame(0, $this->node($this->root)->right_volume);
        $this->assertSame(0, (int) Commission::query()->where('member_id', $this->root->id)->where('type', CommissionType::Binary)->sum('amount'));
        $this->assertLedgersConsistent();
    }

    public function test_refunding_both_sides_of_a_pairing_claws_back_the_commission_only_once()
    {
        foreach (['1', '0'] as $carry) {
            $this->rule(CommissionRule::CARRY_FORWARD_ENABLED, $carry);
            $rootBefore = $this->balance($this->root);

            $rightSale = $this->sell($this->right, 1_000);
            $leftSale = $this->sell($this->leftChild, 1_000);
            $this->runCycle($carry === '1' ? '2026-09-20' : '2026-09-21');

            $this->assertSame($rootBefore + 10_000 + 5_000, $this->balance($this->root), 'binary ৳100 + referral for the right sale ৳50');

            $this->refund($leftSale);
            $this->refund($rightSale);

            $this->assertSame($rootBefore, $this->balance($this->root), "carry={$carry}: root back to where it started");
            $this->assertSame(0, $this->node($this->root)->left_volume);
            $this->assertSame(0, $this->node($this->root)->right_volume);
            $this->assertLedgersConsistent();
        }
    }

    public function test_refund_takes_carried_forward_overflow_back_from_the_deferred_balance()
    {
        $this->rule(CommissionRule::CAP_OVERFLOW_BEHAVIOR, 'carry_forward');
        $this->sell($this->right, 100_000);
        $before = $this->moneyAndVolumeState();

        $sale = $this->sell($this->leftChild, 100_000);
        $this->runCycle();                                              // ৳5,000 paid, ৳5,000 deferred
        $this->assertSame(500_000, $this->node($this->root)->deferred_commission);

        $this->refund($sale);

        $this->assertSame(0, $this->node($this->root)->deferred_commission);
        $this->assertEquals($before, $this->moneyAndVolumeState());
        $this->assertLedgersConsistent();
    }

    public function test_refund_after_deferred_commission_was_released_takes_it_from_the_wallet()
    {
        $this->rule(CommissionRule::CAP_OVERFLOW_BEHAVIOR, 'carry_forward');
        $this->sell($this->right, 100_000);
        $before = $this->moneyAndVolumeState();

        $sale = $this->sell($this->leftChild, 100_000);
        $this->runCycle('2026-09-20');   // ৳5,000 paid + ৳5,000 deferred
        $this->runCycle('2026-09-21');   // deferred ৳5,000 released

        $this->refund($sale);

        $this->assertEquals($before, $this->moneyAndVolumeState());
        $this->assertLedgersConsistent();
    }

    public function test_wallet_may_go_negative_when_the_commission_was_already_spent()
    {
        $this->sell($this->right, 1_000);
        $sale = $this->sell($this->leftChild, 1_000);
        $this->runCycle();

        // Member spends everything (e.g. withdrew it).
        app(WalletService::class)->debit($this->root, $this->balance($this->root), WalletTransactionType::Withdrawal);

        $this->refund($sale);

        $this->assertSame(-10_000, $this->balance($this->root));
        $this->assertLedgersConsistent();
    }

    public function test_reversal_is_idempotent()
    {
        $this->sell($this->right, 1_000);
        $sale = $this->sell($this->leftChild, 1_000);
        $this->runCycle();
        $this->refund($sale);
        $state = $this->moneyAndVolumeState();

        app(CommissionReversalService::class)->reverseSale($sale->fresh());

        $this->assertEquals($state, $this->moneyAndVolumeState());
    }

    public function test_unrefunded_sales_are_not_reversed()
    {
        $sale = $this->sell($this->leftChild, 1_000);
        $state = $this->moneyAndVolumeState();

        app(CommissionReversalService::class)->reverseSale($sale);

        $this->assertEquals($state, $this->moneyAndVolumeState());
        $this->assertNull($sale->fresh()?->reversed_at);
    }
}
