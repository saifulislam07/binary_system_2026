<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\BonusRule;
use App\Models\Member;
use App\Models\Order;
use App\Models\Package;
use App\Models\Rank;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\BuildsNetwork;
use Tests\TestCase;

#[Group('rule-4')]
#[Group('rule-11')]
class CatalogAndRankAdminTest extends TestCase
{
    use BuildsNetwork, RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->superAdmin()->create();
        $this->actingAs($this->admin, 'admin');
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function packageForm(array $overrides = []): array
    {
        return [
            'name' => 'Family', 'description' => 'For households', 'price' => '7500.50', 'bv_value' => '6000',
            'cost_of_goods' => '2500', 'is_qualifying' => '1', 'is_active' => '1', 'sort_order' => '5', ...$overrides,
        ];
    }

    /**
     * @param  callable(Rank): array<string, int>|null  $change
     * @return array<int, array<string, string>>
     */
    private function rankForm(?callable $change = null): array
    {
        return Rank::query()->orderBy('sort_order')->get()->mapWithKeys(function (Rank $rank) use ($change) {
            $values = [
                'min_personal_sales' => $rank->min_personal_sales, 'min_team_sales' => $rank->min_team_sales,
                'min_active_team' => $rank->min_active_team, 'bonus_amount' => $rank->bonus_amount,
                ...($change ? $change($rank) : []),
            ];

            return [$rank->id => [
                'min_personal_sales' => Money::toInputString($values['min_personal_sales']),
                'min_team_sales' => Money::toInputString($values['min_team_sales']),
                'min_active_team' => (string) $values['min_active_team'],
                'bonus_amount' => Money::toInputString($values['bonus_amount']),
            ]];
        })->all();
    }

    public function test_a_new_package_is_stored_in_poysha_and_goes_on_sale()
    {
        $this->post(route('admin.packages.store'), $this->packageForm())->assertRedirect(route('admin.packages.index'));

        $package = Package::query()->where('name', 'Family')->firstOrFail();
        $this->assertSame(750_050, $package->price);
        $this->assertSame(600_000, $package->bv_value);
        $this->assertSame(250_000, $package->cost_of_goods);
        $this->assertSame('family', $package->slug);

        $member = Member::factory()->create();
        $this->actingAs($member->user, 'web')->get(route('checkout.index'))->assertSee('Family');

        $log = Activity::query()->where('log_name', 'catalog')->latest('id')->firstOrFail();
        $this->assertSame('Package created', $log->description);
        $this->assertTrue($log->causer?->is($this->admin));
    }

    public function test_a_price_change_applies_to_the_next_order()
    {
        $package = Package::query()->where('name', 'Basic')->firstOrFail();
        $this->put(route('admin.packages.update', $package), $this->packageForm(['name' => 'Basic', 'price' => '1500', 'bv_value' => '1200']))
            ->assertRedirect(route('admin.packages.index'));

        $log = Activity::query()->where('description', 'Package updated')->firstOrFail();
        $this->assertSame(100_000, $log->properties['old']['price']);
        $this->assertSame(150_000, $log->properties['attributes']['price']);

        config(['payments.simulator' => true]);
        $member = Member::factory()->create(['package_id' => $package->id]);
        $this->actingAs($member->user, 'web')->post(route('checkout.store'), ['package_id' => $package->id, 'gateway' => 'simulator']);
        $this->assertSame(150_000, Order::query()->where('member_id', $member->id)->value('amount'));
    }

    public function test_a_deactivated_package_cannot_be_bought_and_the_last_one_stays_on_sale()
    {
        $packages = Package::query()->orderBy('sort_order')->get();
        [$first, $rest] = [$packages->first(), $packages->slice(1)];

        foreach ($rest as $package) {
            $this->put(route('admin.packages.update', $package), $this->packageForm(['name' => $package->name, 'is_active' => '0']))
                ->assertRedirect(route('admin.packages.index'))
                ->assertSessionHasNoErrors();
        }

        // (The member factory would otherwise add an active package of its own.)
        $member = Member::factory()->create(['package_id' => $first->id]);
        $this->actingAs($member->user, 'web')
            ->post(route('checkout.store'), ['package_id' => $rest->first()->id, 'gateway' => 'bkash'])
            ->assertSessionHasErrors('package_id');

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.packages.update', $first), $this->packageForm(['name' => $first->name, 'is_active' => '0']))
            ->assertSessionHas('error');
        $this->assertTrue($first->fresh()?->is_active, 'The last package on sale stays on sale');
    }

    public function test_package_input_is_validated()
    {
        $this->post(route('admin.packages.store'), $this->packageForm(['name' => 'Basic', 'price' => '10.555', 'bv_value' => '-1']))
            ->assertSessionHasErrors(['name', 'price', 'bv_value']);

        $this->assertSame(4, Package::query()->count());
    }

    public function test_rank_thresholds_are_saved_and_used_by_the_next_evaluation()
    {
        $this->seedCommissionRules();
        $root = $this->root();
        $this->sell($root, 1_000);

        $this->put(route('admin.ranks.update'), ['ranks' => $this->rankForm(fn (Rank $rank) => $rank->name === 'Bronze'
            ? ['min_personal_sales' => 100_000, 'min_team_sales' => 0, 'min_active_team' => 0, 'bonus_amount' => 50_000]
            : [])])
            ->assertRedirect(route('admin.ranks.index'))
            ->assertSessionHas('success');

        $bronze = Rank::query()->where('name', 'Bronze')->firstOrFail();
        $this->assertSame(100_000, $bronze->min_personal_sales);
        $this->assertSame(50_000, $bronze->bonus_amount);
        $this->assertSame('Rank thresholds updated', Activity::query()->where('log_name', 'ranks')->latest('id')->value('description'));

        $this->artisan('ranks:evaluate')->assertSuccessful();
        $this->assertSame($bronze->id, $root->fresh()?->current_rank_id);
    }

    public function test_a_rank_may_not_be_easier_than_the_one_below_it()
    {
        $before = Rank::query()->orderBy('sort_order')->pluck('min_team_sales', 'name');

        $this->put(route('admin.ranks.update'), ['ranks' => $this->rankForm(fn (Rank $rank) => $rank->name === 'Gold' ? ['min_team_sales' => 100] : [])])
            ->assertSessionHas('error', fn (string $message) => str_contains($message, 'Gold needs at least as much as Silver'));

        $this->assertEquals($before, Rank::query()->orderBy('sort_order')->pluck('min_team_sales', 'name'), 'Nothing was saved');
    }

    public function test_bonus_rules_are_created_and_edited_with_the_right_units()
    {
        $this->post(route('admin.bonus-rules.store'), [
            'type' => BonusRule::SALES, 'name' => 'Sales: ৳1 lakh personal', 'threshold' => '100000', 'amount' => '5000', 'is_active' => '1',
        ])->assertRedirect(route('admin.ranks.index'));

        $sales = BonusRule::query()->where('name', 'Sales: ৳1 lakh personal')->firstOrFail();
        $this->assertSame(10_000_000, $sales->threshold, 'sales thresholds are taka → poysha');
        $this->assertSame(500_000, $sales->amount);

        $this->post(route('admin.bonus-rules.store'), [
            'type' => BonusRule::LEADERSHIP, 'name' => 'Leadership: 25', 'threshold' => '25.5', 'amount' => '100', 'is_active' => '1',
        ])->assertSessionHasErrors('threshold'); // leadership counts people

        $this->put(route('admin.bonus-rules.update', $sales), [
            'type' => BonusRule::LEADERSHIP, 'name' => 'x', 'threshold' => '5', 'amount' => '1', 'is_active' => '1',
        ])->assertSessionHasErrors('type');

        $this->put(route('admin.bonus-rules.update', $sales), [
            'name' => 'Sales: ৳1 lakh', 'threshold' => '100000', 'amount' => '6000', 'is_active' => '0',
        ])->assertSessionHasNoErrors();
        $sales->refresh();
        $this->assertSame(600_000, $sales->amount);
        $this->assertFalse($sales->is_active);
        $this->assertSame(BonusRule::SALES, $sales->type);
    }

    public function test_the_pages_render_and_need_manage_settings()
    {
        $this->get(route('admin.packages.index'))->assertOk()->assertSee('Business');
        $this->get(route('admin.packages.create'))->assertOk();
        $this->get(route('admin.packages.edit', Package::query()->firstOrFail()))->assertOk();
        $this->get(route('admin.ranks.index'))->assertOk()->assertSee('Diamond')->assertSee('Leadership');

        $finance = Admin::factory()->create()->assignRole('finance');
        $this->actingAs($finance, 'admin');
        $this->get(route('admin.packages.index'))->assertForbidden();
        $this->post(route('admin.packages.store'), $this->packageForm())->assertForbidden();
        $this->put(route('admin.ranks.update'), ['ranks' => []])->assertForbidden();
    }
}
