<?php

namespace Tests\Feature\Notifications;

use App\Enums\KycStatus;
use App\Enums\PlacementSide;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalMethodType;
use App\Enums\WithdrawalStatus;
use App\Exceptions\DuplicateMemberException;
use App\Models\Admin;
use App\Models\KycDocument;
use App\Models\Member;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\WhatsAppChannel;
use App\Notifications\IncomeReceived;
use App\Notifications\KycStatusChanged;
use App\Notifications\MemberActivated;
use App\Notifications\MemberNotification;
use App\Notifications\OrderCompleted;
use App\Notifications\PasswordChanged;
use App\Notifications\RegistrationReceived;
use App\Notifications\WithdrawalStatusChanged;
use App\Services\KycReviewService;
use App\Services\MatchingService;
use App\Services\PlacementService;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use ReflectionClass;
use Tests\Support\BuildsNetwork;
use Tests\TestCase;

class NotificationEventsTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private const BKASH = ['provider' => 'bkash', 'mobile_number' => '+8801712345678'];

    public function test_registration_sends_the_confirmation()
    {
        Notification::fake();
        $sponsor = $this->root();

        $this->post(route('register.store'), [
            'name' => 'Nusrat Jahan', 'email' => 'nusrat@example.com', 'phone' => '01812345678', 'nid' => '5551234567',
            'address' => 'Mirpur, Dhaka', 'sponsor_code' => $sponsor->member_code, 'preferred_side' => 'right',
            'package_id' => Package::query()->firstOrFail()->id, 'password' => 'password', 'password_confirmation' => 'password',
        ]);

        $user = User::query()->where('email', 'nusrat@example.com')->firstOrFail();
        Notification::assertSentToTimes($user, RegistrationReceived::class, 1);
        Notification::assertNotSentTo($user, MemberActivated::class);
    }

    public function test_activation_notifies_once_even_when_repeated()
    {
        Notification::fake();
        $member = Member::factory()->create();

        app(PlacementService::class)->activateMember($member);
        app(PlacementService::class)->activateMember($member); // repeated payment callback

        Notification::assertSentToTimes($member->user, MemberActivated::class, 1);
        Notification::assertSentTo($member->user, MemberActivated::class, fn (MemberActivated $n) => $n->member->member_code === 'MBR-100001');
    }

    public function test_a_paid_order_notifies_the_buyer_and_the_sponsor_gets_commission_notice()
    {
        Notification::fake();
        config(['payments.simulator' => true]);
        $sponsor = $this->root();
        $buyer = Member::factory()->create(['sponsor_id' => $sponsor->id]);
        $package = Package::query()->where('is_qualifying', true)->firstOrFail();

        $this->actingAs($buyer->user)->post(route('checkout.store'), ['package_id' => $package->id, 'gateway' => 'simulator']);
        $order = Order::query()->where('member_id', $buyer->id)->firstOrFail();
        $this->get(route('payments.callback', ['gateway' => 'simulator', 'ref' => $order->payments()->firstOrFail()->gateway_ref, 'status' => 'success']));

        Notification::assertSentTo($buyer->user, MemberActivated::class);
        Notification::assertSentTo($buyer->user, OrderCompleted::class, fn (OrderCompleted $n) => $n->sale->order_id === $order->id);
        Notification::assertSentTo($sponsor->user, IncomeReceived::class,
            fn (IncomeReceived $n) => $n->transaction->type === WalletTransactionType::ReferralBonus);
    }

    public function test_binary_commission_notifies_the_upline()
    {
        $this->seedCommissionRules();
        $root = $this->root();
        $this->sell($this->join($root, PlacementSide::Left), 1_000);
        $this->sell($this->join($root, PlacementSide::Right), 1_000);

        Notification::fake();
        app(MatchingService::class)->runCycle(Carbon::parse('2026-09-20'));

        Notification::assertSentTo($root->user, IncomeReceived::class,
            fn (IncomeReceived $n) => $n->transaction->type === WalletTransactionType::BinaryCommission && $n->transaction->amount === 10_000);
    }

    public function test_adjustments_and_withdrawal_holds_are_not_income()
    {
        Notification::fake();
        $member = Member::factory()->active()->create();

        app(WalletService::class)->credit($member, 500_000, WalletTransactionType::Adjustment);
        app(WalletService::class)->debit($member, 100_000, WalletTransactionType::Adjustment);

        Notification::assertNotSentTo($member->user, IncomeReceived::class);
    }

    public function test_every_withdrawal_transition_notifies_the_member()
    {
        Notification::fake();
        $member = Member::factory()->active()->create();
        $admin = Admin::factory()->superAdmin()->create();
        app(WalletService::class)->credit($member, 1_000_000, WalletTransactionType::ReferralBonus);
        $withdrawals = app(WithdrawalService::class);

        $paid = $withdrawals->request($member, 200_000, WithdrawalMethodType::MobileBanking, self::BKASH);
        $withdrawals->approve($paid, $admin);
        $withdrawals->startProcessing($paid, $admin);
        $withdrawals->markPaid($paid, $admin, 'BKASH-TRX-1');

        $rejected = $withdrawals->request($member, 150_000, WithdrawalMethodType::MobileBanking, self::BKASH);
        $withdrawals->reject($rejected, $admin, 'Account name does not match');

        $sent = Notification::sent($member->user, WithdrawalStatusChanged::class);
        $this->assertSame(
            ['pending', 'approved', 'processing', 'paid', 'pending', 'rejected'],
            $sent->map(fn (WithdrawalStatusChanged $n) => $n->status->value)->all(),
        );

        $rejection = $sent->last();
        $this->assertInstanceOf(WithdrawalStatusChanged::class, $rejection);
        $this->assertStringContainsString('Account name does not match', $rejection->message($member->user));
        $this->assertSame(WithdrawalStatus::Rejected, $rejection->status);
    }

    public function test_kyc_decisions_notify_the_member()
    {
        Notification::fake();
        $admin = Admin::factory()->superAdmin()->create();
        $approved = KycDocument::factory()->create();
        $rejected = KycDocument::factory()->create();

        app(KycReviewService::class)->approve($approved, $admin);
        app(KycReviewService::class)->reject($rejected, $admin, 'Photo is blurred');

        Notification::assertSentTo($approved->member->user, KycStatusChanged::class, fn (KycStatusChanged $n) => $n->status === KycStatus::Approved);
        Notification::assertSentTo($rejected->member->user, KycStatusChanged::class,
            fn (KycStatusChanged $n) => $n->status === KycStatus::Rejected && str_contains($n->message($rejected->member->user), 'Photo is blurred'));
    }

    public function test_password_reset_and_change_notify_the_member()
    {
        Notification::fake();
        $user = Member::factory()->active()->create()->user;

        $this->post(route('password.update'), [
            'token' => Password::createToken($user),
            'email' => $user->email,
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertSessionHasNoErrors();
        Notification::assertSentToTimes($user, PasswordChanged::class, 1);

        $user->refresh();
        $this->actingAs($user)->put(route('user-password.update'), [
            'current_password' => 'new-password-123',
            'password' => 'another-password-456',
            'password_confirmation' => 'another-password-456',
        ])->assertSessionHasNoErrors();
        Notification::assertSentToTimes($user, PasswordChanged::class, 2);
    }

    public function test_every_notification_is_queued()
    {
        $classes = collect(glob(app_path('Notifications/*.php')) ?: [])
            ->map(fn (string $file) => 'App\\Notifications\\'.basename($file, '.php'))
            ->filter(fn (string $class) => is_subclass_of($class, BaseNotification::class) && ! (new ReflectionClass($class))->isAbstract());

        $this->assertGreaterThanOrEqual(9, $classes->count());

        foreach ($classes as $class) {
            $this->assertTrue(is_subclass_of($class, ShouldQueue::class), "{$class} must be queued");
        }
    }

    public function test_channels_follow_config_and_urgency()
    {
        $member = app(PlacementService::class)->activateMember(Member::factory()->create());
        $user = $member->user;
        $sale = $this->sell($member, 1_000);

        $this->assertSame(['database', EmailChannel::class], (new MemberActivated($member))->via($user));

        config(['notifications.channels.sms' => true, 'notifications.channels.whatsapp' => true]);
        $this->assertSame(['database', EmailChannel::class, SmsChannel::class, WhatsAppChannel::class], (new MemberActivated($member))->via($user));
        $this->assertSame(['database', EmailChannel::class], (new OrderCompleted($sale))->via($user), 'Not urgent: no text message');

        config(['notifications.channels.mail' => false]);
        $this->assertSame(['database'], (new OrderCompleted($sale))->via($user));
    }

    public function test_the_sms_stub_logs_instead_of_sending()
    {
        config(['notifications.channels.sms' => true, 'notifications.stub_log_channel' => 'null']);
        $member = Member::factory()->create();

        Log::shouldReceive('channel')->with('null')->andReturnSelf();
        Log::shouldReceive('info')->once()->withArgs(fn (string $message, array $context) => str_contains($message, '[sms stub]')
            && $context['to'] === $member->user->phone
            && str_contains($context['text'], 'MBR-100001'));
        Log::shouldReceive('info')->withAnyArgs(); // whatsapp is off; other loggers stay quiet

        app(PlacementService::class)->activateMember($member);
    }

    public function test_notifications_wait_for_the_transaction_and_are_stored_for_the_bell()
    {
        $first = app(PlacementService::class)->activateMember(Member::factory()->create(['nid' => '1111111111']));

        $stored = $first->user->notifications()->firstOrFail();
        $this->assertInstanceOf(DatabaseNotification::class, $stored);
        $this->assertSame(MemberActivated::class, $stored->type);
        $this->assertSame('activation', $stored->data['kind']);
        $this->assertSame('/dashboard', $stored->data['url']);
        $this->assertStringContainsString('MBR-100001', $stored->data['message']);

        // A rolled-back activation must not notify.
        $duplicate = Member::factory()->create(['nid' => '1111111111', 'sponsor_id' => $first->id]);

        try {
            app(PlacementService::class)->activateMember($duplicate);
            $this->fail('Duplicate activated');
        } catch (DuplicateMemberException) {
        }

        $this->assertSame(0, $duplicate->user->notifications()->count());
    }

    public function test_member_notifications_extend_the_base_class()
    {
        foreach ([RegistrationReceived::class, MemberActivated::class, OrderCompleted::class, IncomeReceived::class,
            WithdrawalStatusChanged::class, KycStatusChanged::class, PasswordChanged::class] as $class) {
            $this->assertTrue(is_subclass_of($class, MemberNotification::class), $class);
        }
    }
}
