<?php

namespace Tests\Feature\Ranks;

use App\Enums\BonusType;
use App\Enums\PlacementSide;
use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\Bonus;
use App\Models\Member;
use App\Models\WalletTransaction;
use App\Services\AdminDashboardService;
use App\Services\RankService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\BuildsNetwork;
use Tests\TestCase;

class AdminRankAndBonusTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Admin $admin;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCommissionRules();
        $this->admin = Admin::factory()->superAdmin()->create();
        $root = $this->root();
        $this->member = $this->join($root, PlacementSide::Left);
    }

    public function test_super_admin_pays_a_logged_performance_bonus()
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.members.performance-bonus', $this->member), ['amount' => '2,500', 'reason' => 'Top seller in September'])
            ->assertRedirect(route('admin.members.show', $this->member))
            ->assertSessionHas('success');

        $bonus = Bonus::query()->firstOrFail();
        $this->assertSame(BonusType::Performance, $bonus->type);
        $this->assertSame(250_000, $bonus->amount);
        $this->assertSame($this->admin->id, $bonus->awarded_by);
        $this->assertSame(250_000, $this->balance($this->member));
        $this->assertSame(1, WalletTransaction::query()->where('type', WalletTransactionType::PerformanceBonus)->count());
        $this->assertSame($this->admin->id, Activity::query()->where('description', 'Bonus paid')->firstOrFail()->causer_id);

        // It counts as a payout in the company P&L.
        $this->assertSame(250_000, app(AdminDashboardService::class)->metrics('month')['commission_paid']);
    }

    public function test_performance_bonus_is_super_admin_only_and_validated()
    {
        $finance = Admin::factory()->create()->assignRole('finance');

        $this->actingAs($finance, 'admin')
            ->post(route('admin.members.performance-bonus', $this->member), ['amount' => '100', 'reason' => 'Trying it'])
            ->assertForbidden();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.members.performance-bonus', $this->member), ['amount' => 'lots', 'reason' => 'Some reason'])
            ->assertSessionHas('error');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.members.performance-bonus', $this->member), ['amount' => '100', 'reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, Bonus::query()->count());
    }

    public function test_rank_report_shows_the_distribution()
    {
        $this->sell($this->member, 5_000);
        app(RankService::class)->evaluate($this->member);

        $rows = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.reports.ranks'))
            ->assertOk()
            ->viewData('rows')
            ->keyBy('name');

        // Root is still unranked (counted as Member); the joined member reached Member too
        // (Bronze needs team volume they don't have).
        $this->assertSame(2, $rows['Member']['members']);
        $this->assertSame(0, $rows['Bronze']['members']);
        $this->assertSame(1, $rows['Member']['achieved']);
    }
}
