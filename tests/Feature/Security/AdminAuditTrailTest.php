<?php

namespace Tests\Feature\Security;

use App\Enums\ExpenseCategory;
use App\Enums\PlacementSide;
use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalMethodType;
use App\Http\Controllers\Admin\FinancialController;
use App\Models\Admin;
use App\Models\Expense;
use App\Models\FraudFlag;
use App\Models\IncomeTransaction;
use App\Models\KycDocument;
use App\Models\Member;
use App\Models\Package;
use App\Models\Withdrawal;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\BuildsNetwork;
use Tests\TestCase;

/**
 * Rule #12: every admin action that changes money, tree structure or member
 * status leaves an audit entry naming the admin. The route list is checked
 * too, so a new admin write endpoint fails here until it is covered.
 */
#[Group('rule-12')]
class AdminAuditTrailTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private const BKASH = ['provider' => 'bkash', 'mobile_number' => '+8801712345678'];

    /** Session plumbing, not business actions. */
    private const NOT_AUDITED = ['admin.login.store', 'admin.logout'];

    private Admin $admin;

    private Member $root;

    private Member $left;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCommissionRules();
        $this->admin = Admin::factory()->superAdmin()->create();
        $this->root = $this->root();
        $this->left = $this->join($this->root, PlacementSide::Left);
    }

    private function withdrawal(): Withdrawal
    {
        app(WalletService::class)->credit($this->left, 500_000, WalletTransactionType::ReferralBonus);

        return app(WithdrawalService::class)->request($this->left, 150_000, WithdrawalMethodType::MobileBanking, self::BKASH);
    }

    /**
     * Route name => [method, url, payload] built against fresh data.
     *
     * @return array<string, callable(): array{0: string, 1: string, 2: array<string, mixed>}>
     */
    private function actions(): array
    {
        return [
            'admin.members.update' => fn () => ['PUT', route('admin.members.update', $this->left), [
                'name' => 'Renamed Member', 'email' => 'renamed@example.com', 'phone' => '01911111111', 'nid' => '1234567890', 'address' => 'Khulna',
            ]],
            'admin.members.suspend' => fn () => ['POST', route('admin.members.suspend', $this->left), ['reason' => 'Duplicate account']],
            'admin.members.reinstate' => function () {
                $this->post(route('admin.members.suspend', $this->left), ['reason' => 'Temporary hold']);

                return ['POST', route('admin.members.reinstate', $this->left), ['reason' => 'Cleared']];
            },
            'admin.members.package' => fn () => ['POST', route('admin.members.package', $this->left), [
                'package_id' => Package::query()->where('name', 'Premium')->value('id'), 'reason' => 'Upgrade agreed by phone',
            ]],
            'admin.members.activate' => fn () => ['POST', route('admin.members.activate', Member::factory()->create(['sponsor_id' => $this->root->id])), []],
            'admin.members.performance-bonus' => fn () => ['POST', route('admin.members.performance-bonus', $this->left), ['amount' => '500', 'reason' => 'Top seller in September']],
            'admin.tree.adjust' => function () {
                $mover = $this->join($this->left, PlacementSide::Left);

                return ['POST', route('admin.tree.adjust', $mover->member_code), [
                    'new_parent' => $this->root->member_code, 'side' => 'right', 'reason' => 'Placed on the wrong leg at signup',
                ]];
            },
            'admin.sales.refund' => fn () => ['POST', route('admin.sales.refund', $this->sell($this->left, 1_000)), ['reason' => 'Product returned']],
            'admin.withdrawals.approve' => fn () => ['POST', route('admin.withdrawals.approve', $this->withdrawal()), []],
            'admin.withdrawals.processing' => function () {
                $w = $this->withdrawal();
                app(WithdrawalService::class)->approve($w, $this->admin);

                return ['POST', route('admin.withdrawals.processing', $w), []];
            },
            'admin.withdrawals.paid' => function () {
                $w = $this->withdrawal();
                app(WithdrawalService::class)->approve($w, $this->admin);
                app(WithdrawalService::class)->startProcessing($w, $this->admin);

                return ['POST', route('admin.withdrawals.paid', $w), ['payout_reference' => 'BK-TRX-99']];
            },
            'admin.withdrawals.reject' => fn () => ['POST', route('admin.withdrawals.reject', $this->withdrawal()), ['reason' => 'Name mismatch']],
            'admin.kyc.approve' => fn () => ['POST', route('admin.kyc.approve', KycDocument::factory()->create(['member_id' => $this->left->id])), []],
            'admin.kyc.reject' => fn () => ['POST', route('admin.kyc.reject', KycDocument::factory()->create(['member_id' => $this->left->id])), ['reason' => 'Blurred']],
            'admin.fraud.review' => fn () => ['POST', route('admin.fraud.review', FraudFlag::raise($this->left, FraudFlag::RAPID_WITHDRAWAL, null, [])), [
                'outcome' => 'reviewed', 'note' => 'Checked with the member',
            ]],
            'admin.financial.expenses.store' => fn () => ['POST', route('admin.financial.expenses.store'), [
                'category' => ExpenseCategory::Delivery->value, 'amount' => '250', 'date' => now()->toDateString(), 'description' => 'Courier',
            ]],
            'admin.financial.expenses.destroy' => fn () => ['DELETE', route('admin.financial.expenses.destroy', Expense::factory()->create()), []],
            'admin.financial.income.store' => fn () => ['POST', route('admin.financial.income.store'), [
                'source' => array_key_first(FinancialController::INCOME_SOURCES), 'amount' => '100', 'date' => now()->toDateString(), 'description' => 'Bank interest',
            ]],
            'admin.financial.income.destroy' => fn () => ['DELETE', route('admin.financial.income.destroy', IncomeTransaction::factory()->create()), []],
            'admin.settings.update' => fn () => ['PUT', route('admin.settings.update'), [
                'binary_rate' => '11', 'referral_rate' => '5', 'daily_cap' => '5000', 'weekly_cap' => '20000', 'monthly_cap' => '50000',
                'carry_forward_enabled' => '1', 'cap_overflow_behavior' => 'void', 'min_withdrawal' => '1000',
            ]],
            'admin.announcements.store' => fn () => ['POST', route('admin.announcements.store'), ['title' => 'Notice', 'body' => 'Office closed', 'audience' => 'active']],
        ];
    }

    public function test_every_admin_write_endpoint_is_covered_here()
    {
        $writes = collect(Router::getRoutes()->getRoutes())
            ->filter(fn (Route $r) => str_starts_with($r->uri(), 'admin/') && array_diff($r->methods(), ['GET', 'HEAD']) !== [])
            ->map(fn (Route $r) => (string) $r->getName())
            ->reject(fn (string $name) => in_array($name, self::NOT_AUDITED, true))
            ->sort()->values()->all();

        $covered = collect(array_keys($this->actions()))->sort()->values()->all();

        $this->assertSame($writes, $covered, 'A new admin write endpoint needs an audit-trail case here.');
    }

    public function test_every_admin_write_is_audited_with_the_admin_as_causer()
    {
        $this->actingAs($this->admin, 'admin');

        foreach ($this->actions() as $name => $action) {
            [$method, $url, $payload] = $action();
            $before = (int) Activity::query()->max('id');

            $this->call($method, $url, $payload)->assertRedirect()->assertSessionHasNoErrors()->assertSessionMissing('error');

            $entries = Activity::query()->with('causer')->where('id', '>', $before)->get();
            $this->assertTrue(
                $entries->contains(fn (Activity $a) => $a->causer instanceof Admin && $a->causer->is($this->admin)),
                "{$name} left no audit entry caused by the admin (got: ".$entries->pluck('description')->implode(', ').')',
            );
        }
    }
}
