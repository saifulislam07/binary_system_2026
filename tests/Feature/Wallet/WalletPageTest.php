<?php

namespace Tests\Feature\Wallet;

use App\Enums\PlacementSide;
use App\Enums\WalletTransactionType;
use App\Models\Member;
use App\Models\User;
use App\Services\MatchingService;
use App\Services\RefundService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\BuildsNetwork;
use Tests\TestCase;

class WalletPageTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    public function test_wallet_page_shows_balance_and_transactions()
    {
        $member = Member::factory()->active()->create();
        app(WalletService::class)->credit($member, 125_050, WalletTransactionType::ReferralBonus, description: 'Welcome');
        app(WalletService::class)->debit($member, 25_050, WalletTransactionType::Withdrawal);

        $this->actingAs($member->user)
            ->get(route('wallet.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('wallet/Index')
                ->where('summary.available', '৳1,000.00')
                ->has('transactions.data', 2)
                ->where('transactions.data.0.amount', '−৳250.50')
                ->where('transactions.data.0.credit', false)
                ->where('transactions.data.1.amount', '+৳1,250.50')
                ->where('transactions.data.1.typeLabel', WalletTransactionType::ReferralBonus->label())
                ->where('transactions.data.1.description', 'Welcome')
                ->where('transactions.data.1.balanceAfter', '৳1,250.50'));
    }

    public function test_wallet_page_filters_and_paginates()
    {
        $member = Member::factory()->active()->create();
        $wallets = app(WalletService::class);

        for ($i = 0; $i < 25; $i++) {
            $wallets->credit($member, 100, WalletTransactionType::BinaryCommission);
        }
        $wallets->credit($member, 100, WalletTransactionType::ReferralBonus);

        $this->actingAs($member->user)
            ->get(route('wallet.index', ['type' => 'referral_bonus']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions.data', 1)
                ->where('filters.type', 'referral_bonus'));

        $this->actingAs($member->user)
            ->get(route('wallet.index', ['page' => 2]))
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions.data', 6)
                ->where('transactions.current_page', 2)
                ->where('transactions.last_page', 2));

        $tomorrow = now()->addDay()->toDateString();
        $this->actingAs($member->user)
            ->get(route('wallet.index', ['from' => $tomorrow]))
            ->assertInertia(fn (Assert $page) => $page->has('transactions.data', 0));
    }

    public function test_invalid_filters_are_rejected()
    {
        $member = Member::factory()->active()->create();

        $this->actingAs($member->user)
            ->get(route('wallet.index', ['type' => 'bogus', 'from' => '2026-09-10', 'to' => '2026-09-01']))
            ->assertSessionHasErrors(['type', 'to']);
    }

    public function test_members_only_see_their_own_transactions()
    {
        $mine = Member::factory()->active()->create();
        $theirs = Member::factory()->active()->create();
        app(WalletService::class)->credit($theirs, 99_900, WalletTransactionType::Adjustment);

        $this->actingAs($mine->user)
            ->get(route('wallet.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('transactions.data', 0)
                ->where('summary.available', '৳0.00'));
    }

    public function test_users_without_a_member_record_cannot_open_the_wallet()
    {
        $this->actingAs(User::factory()->create())->get(route('wallet.index'))->assertForbidden();
    }

    public function test_guests_are_sent_to_login()
    {
        $this->get(route('wallet.index'))->assertRedirect(route('login'));
    }

    public function test_dashboard_widget_shows_period_referral_last_cycle_binary_and_net_lifetime_income()
    {
        $this->seedCommissionRules();
        Carbon::setTestNow('2026-09-20 12:00');

        $root = $this->root();
        $left = $this->join($root, PlacementSide::Left);
        $right = $this->join($root, PlacementSide::Right);

        $this->sell($left, 1_000);                         // referral ৳50 to root
        $this->sell($right, 1_000);                        // referral ৳50 to root
        app(MatchingService::class)->runCycle(Carbon::parse('2026-09-20')); // binary ৳100 to root

        Carbon::setTestNow('2026-09-21 12:00');
        $refunded = $this->sell($left, 2_000);             // referral ৳100 today …
        app(RefundService::class)->refund($refunded, 'test'); // … then reversed

        $this->actingAs($root->user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('walletSummary.available', '৳200.00')
                ->where('walletSummary.period', 'Today')
                ->where('walletSummary.referral', '৳0.00')          // ৳100 earned today, ৳100 reversed
                ->where('walletSummary.binary', '৳100.00')
                ->where('walletSummary.binaryCycle', '2026-09-20')
                ->where('walletSummary.lifetime', '৳200.00'));     // 50 + 50 + 100 + 100 − 100

        Carbon::setTestNow();
    }

    public function test_pending_members_see_no_wallet_widget()
    {
        $pending = Member::factory()->create();

        $this->actingAs($pending->user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('walletSummary', null));
    }
}
