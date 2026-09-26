<?php

namespace Tests\Feature\Fraud;

use App\Enums\WalletTransactionType;
use App\Enums\WithdrawalMethodType;
use App\Models\Admin;
use App\Models\Member;
use App\Services\MemberAdminService;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditLogViewerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private Admin $other;

    private Member $alice;

    private Member $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->superAdmin()->create(['name' => 'Rahim Admin']);
        $this->other = Admin::factory()->superAdmin()->create(['name' => 'Karim Admin']);
        $this->alice = Member::factory()->active()->create();
        $this->bob = Member::factory()->active()->create();

        $wallets = app(WalletService::class);
        $wallets->credit($this->alice, 300_000, WalletTransactionType::ReferralBonus);
        $wallets->credit($this->bob, 300_000, WalletTransactionType::ReferralBonus);

        $withdrawal = app(WithdrawalService::class)->request($this->alice, 150_000, WithdrawalMethodType::MobileBanking, ['provider' => 'bkash', 'mobile_number' => '+8801712345678']);
        app(WithdrawalService::class)->approve($withdrawal, $this->admin);
        app(MemberAdminService::class)->suspend($this->bob, $this->other, 'Duplicate account');
    }

    public function test_every_entry_is_listed_newest_first()
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.audit.index'))
            ->assertOk()
            ->assertViewHas('entries', fn ($page) => $page->total() === Activity::query()->count()
                && $page->first()->id === Activity::query()->max('id'));
    }

    public function test_filter_by_affected_member_includes_their_wallet_and_withdrawals()
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.audit.index', ['member' => strtolower((string) $this->alice->member_code)]))
            ->assertOk()
            ->assertViewHas('entries', function ($page) {
                $descriptions = collect($page->items())->pluck('description');

                return $descriptions->contains('Withdrawal requested')
                    && $descriptions->contains('Withdrawal approved')
                    && collect($page->items())->every(fn (Activity $a) => ! ($a->subject instanceof Member && $a->subject->is($this->bob)));
            });

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.audit.index', ['member' => 'MBR-999999']))
            ->assertViewHas('memberNotFound', true)
            ->assertViewHas('entries', fn ($page) => $page->total() === 0);
    }

    public function test_filter_by_actor_type_and_date()
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.audit.index', ['actor' => $this->other->id]))
            ->assertViewHas('entries', fn ($page) => $page->total() > 0
                && collect($page->items())->every(fn (Activity $a) => $a->causer?->is($this->other)));

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.audit.index', ['type' => 'withdrawals']))
            ->assertViewHas('entries', fn ($page) => $page->total() > 0
                && collect($page->items())->every(fn (Activity $a) => $a->log_name === 'withdrawals'));

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.audit.index', ['admin_only' => 1]))
            ->assertViewHas('entries', fn ($page) => collect($page->items())->every(fn (Activity $a) => $a->causer instanceof Admin));

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.audit.index', ['from' => now()->addDay()->format('Y-m-d')]))
            ->assertViewHas('entries', fn ($page) => $page->total() === 0);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.audit.index', ['from' => now()->format('Y-m-d'), 'to' => now()->format('Y-m-d')]))
            ->assertViewHas('entries', fn ($page) => $page->total() === Activity::query()->count());
    }

    public function test_only_super_admins_see_the_audit_log()
    {
        $support = Admin::factory()->create()->assignRole('support');

        $this->actingAs($support, 'admin')->get(route('admin.audit.index'))->assertForbidden();
    }
}
