<?php

namespace Tests\Feature\Withdrawals;

use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalMethodType;
use App\Enums\WithdrawalStatus;
use App\Models\Admin;
use App\Models\Member;
use App\Models\Withdrawal;
use App\Models\WithdrawalMethod;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WithdrawalPageTest extends TestCase
{
    use RefreshDatabase;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->member = Member::factory()->active()->create();
        app(WalletService::class)->credit($this->member, 500_000, WalletTransactionType::BinaryCommission);
    }

    public function test_page_shows_balance_minimum_and_history()
    {
        app(WithdrawalService::class)->request($this->member, 150_000, WithdrawalMethodType::MobileBanking, ['provider' => 'nagad', 'mobile_number' => '+8801812345678']);

        $this->actingAs($this->member->user)
            ->get(route('withdrawals.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('withdrawals/Index')
                ->where('canRequest', true)
                ->where('balance', '৳3,500.00')
                ->where('minimum', '৳1,000.00')
                ->has('withdrawals.data', 1)
                ->where('withdrawals.data.0.amount', '৳1,500.00')
                ->where('withdrawals.data.0.account', 'Nagad · 01812***678')
                ->where('withdrawals.data.0.status', 'pending'));
    }

    public function test_member_can_request_to_a_new_mobile_account_and_save_it()
    {
        $this->actingAs($this->member->user)
            ->post(route('withdrawals.store'), [
                'amount' => '1500.50',
                'method' => 'mobile_banking',
                'provider' => 'bkash',
                'mobile_number' => '01712-345678',
                'save_method' => '1',
            ])
            ->assertRedirect(route('withdrawals.index'))
            ->assertSessionHasNoErrors();

        $withdrawal = Withdrawal::query()->firstOrFail();
        $this->assertSame(150_050, $withdrawal->amount);
        $this->assertSame(['provider' => 'bkash', 'mobile_number' => '+8801712345678'], $withdrawal->account_details);
        $this->assertSame(349_950, app(WalletService::class)->balance($this->member));

        $saved = WithdrawalMethod::query()->where('member_id', $this->member->id)->firstOrFail();
        $this->assertTrue($saved->is_default);
    }

    public function test_member_can_request_to_a_saved_bank_account()
    {
        $method = WithdrawalMethod::factory()->create([
            'member_id' => $this->member->id,
            'type' => WithdrawalMethodType::Bank,
            'details' => ['bank_name' => 'Sonali Bank', 'branch_name' => 'Motijheel', 'account_name' => 'Rahim', 'account_number' => '0012345678'],
        ]);

        $this->actingAs($this->member->user)
            ->post(route('withdrawals.store'), ['amount' => '2000', 'withdrawal_method_id' => $method->id])
            ->assertSessionHasNoErrors();

        $withdrawal = Withdrawal::query()->firstOrFail();
        $this->assertSame(WithdrawalMethodType::Bank, $withdrawal->method);
        $this->assertSame('0012345678', $withdrawal->account_details['account_number']);
    }

    public function test_cannot_use_another_members_saved_account()
    {
        $foreign = WithdrawalMethod::factory()->create(); // belongs to someone else

        $this->actingAs($this->member->user)
            ->post(route('withdrawals.store'), ['amount' => '2000', 'withdrawal_method_id' => $foreign->id])
            ->assertSessionHasErrors('withdrawal_method_id');

        $this->assertSame(0, Withdrawal::query()->count());
    }

    public function test_amount_below_minimum_or_above_balance_is_rejected_at_validation()
    {
        $details = ['method' => 'mobile_banking', 'provider' => 'bkash', 'mobile_number' => '01712345678'];

        $this->actingAs($this->member->user)
            ->post(route('withdrawals.store'), ['amount' => '999.99', ...$details])
            ->assertSessionHasErrors(['amount' => 'The minimum withdrawal is ৳1,000.00.']);

        $this->actingAs($this->member->user)
            ->post(route('withdrawals.store'), ['amount' => '5000.01', ...$details])
            ->assertSessionHasErrors(['amount' => 'You can withdraw at most ৳5,000.00.']);

        $this->assertSame(0, Withdrawal::query()->count());
        $this->assertSame(500_000, app(WalletService::class)->balance($this->member));
    }

    public function test_new_account_fields_are_validated()
    {
        $this->actingAs($this->member->user)
            ->post(route('withdrawals.store'), ['amount' => '2000', 'method' => 'bank', 'account_number' => '12'])
            ->assertSessionHasErrors(['bank_name', 'branch_name', 'account_name', 'account_number']);

        $this->actingAs($this->member->user)
            ->post(route('withdrawals.store'), ['amount' => '2000', 'method' => 'mobile_banking', 'provider' => 'paypal', 'mobile_number' => '12345'])
            ->assertSessionHasErrors(['provider', 'mobile_number']);

        $this->actingAs($this->member->user)
            ->post(route('withdrawals.store'), ['amount' => 'lots'])
            ->assertSessionHasErrors(['amount', 'method']);
    }

    public function test_pending_members_see_the_page_but_cannot_request()
    {
        $pending = Member::factory()->create();

        $this->actingAs($pending->user)
            ->get(route('withdrawals.index'))
            ->assertInertia(fn (Assert $page) => $page->where('canRequest', false));

        $this->actingAs($pending->user)
            ->post(route('withdrawals.store'), ['amount' => '2000', 'method' => 'mobile_banking', 'provider' => 'bkash', 'mobile_number' => '01712345678'])
            ->assertForbidden();
    }

    public function test_history_shows_rejection_reason_and_status()
    {
        $withdrawal = app(WithdrawalService::class)->request($this->member, 150_000, WithdrawalMethodType::MobileBanking, ['provider' => 'bkash', 'mobile_number' => '+8801712345678']);
        app(WithdrawalService::class)->reject($withdrawal, Admin::factory()->superAdmin()->create(), 'Wrong number');

        $this->actingAs($this->member->user)
            ->get(route('withdrawals.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('balance', '৳5,000.00')
                ->where('withdrawals.data.0.status', WithdrawalStatus::Rejected->value)
                ->where('withdrawals.data.0.statusLabel', WithdrawalStatus::Rejected->label())
                ->where('withdrawals.data.0.rejectionReason', 'Wrong number'));
    }

    public function test_members_only_see_their_own_withdrawals()
    {
        $other = Member::factory()->active()->create();
        app(WalletService::class)->credit($other, 500_000, WalletTransactionType::Adjustment);
        app(WithdrawalService::class)->request($other, 150_000, WithdrawalMethodType::MobileBanking, ['provider' => 'bkash', 'mobile_number' => '+8801712345678']);

        $this->actingAs($this->member->user)
            ->get(route('withdrawals.index'))
            ->assertInertia(fn (Assert $page) => $page->has('withdrawals.data', 0));
    }
}
