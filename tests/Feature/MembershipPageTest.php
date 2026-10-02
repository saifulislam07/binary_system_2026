<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BonusRule;
use App\Models\CommissionRule;
use App\Models\MembershipSection;
use App\Models\Package;
use App\Models\Rank;
use App\Services\MembershipPageService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * The public membership page is live: admin-written sections (in the
 * visitor's language) plus the current packages, ranks and bonus rules.
 * The earnings disclaimer is part of the page and can't be edited away.
 */
class MembershipPageTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->superAdmin()->create();

        // Start without the starter sections ReferenceDataSeeder adds.
        MembershipSection::query()->delete();
    }

    public function test_admins_write_sections_in_both_languages()
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.membership.store'), [
                'title_en' => 'Who can join',
                'title_bn' => 'কে যোগ দিতে পারেন',
                'body_en' => '<p>Any <strong>adult</strong>.</p><script>alert(1)</script>',
                'body_bn' => '<p>১৮ বছর বা তার বেশি বয়সী যে কেউ।</p>',
                'sort_order' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.membership.index'));

        $section = MembershipSection::query()->where('title_en', 'Who can join')->firstOrFail();
        $this->assertSame('<p>Any <strong>adult</strong>.</p>', $section->body_en);

        $log = Activity::query()->where('description', 'Membership page section created')->firstOrFail();
        $this->assertTrue($log->causer?->is($this->admin));

        $this->get(route('membership'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sections.0.title', 'Who can join')
                ->where('sections.0.body', '<p>Any <strong>adult</strong>.</p>'));

        $this->withSession(['locale' => 'bn'])
            ->get(route('membership'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sections.0.title', 'কে যোগ দিতে পারেন')
                ->where('sections.0.body', '<p>১৮ বছর বা তার বেশি বয়সী যে কেউ।</p>'));

        // Editing logs old and new; hiding takes it off the page.
        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.membership.update', $section), [
                'title_en' => 'Who can join', 'body_en' => '<p>Any adult.</p>', 'sort_order' => '1', 'is_active' => '0',
            ])
            ->assertRedirect(route('admin.membership.index'));

        $update = Activity::query()->where('description', 'Membership page section updated')->firstOrFail();
        $this->assertTrue($update->properties['old']['is_active']);
        $this->assertNull($section->refresh()->title_bn);

        $this->get(route('membership'))->assertInertia(fn (Assert $page) => $page->has('sections', 0));
    }

    public function test_bangla_readers_see_english_where_no_translation_was_written()
    {
        MembershipSection::query()->create(['title_en' => 'Fair play', 'body_en' => '<p>Genuine sales only.</p>', 'body_bn' => '<p><br></p>']);

        $this->withSession(['locale' => 'bn'])
            ->get(route('membership'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sections.0.title', 'Fair play')
                ->where('sections.0.body', '<p>Genuine sales only.</p>'));
    }

    public function test_section_input_is_validated_and_gated()
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.membership.store'), ['title_en' => '', 'body_en' => '<p><br></p>', 'sort_order' => '1', 'is_active' => '1'])
            ->assertSessionHasErrors(['title_en', 'body_en']);

        $support = Admin::factory()->create()->assignRole('support');
        $this->actingAs($support, 'admin')->get(route('admin.membership.index'))->assertForbidden();
    }

    public function test_the_page_shows_live_packages_ranks_and_bonus_rules()
    {
        Package::query()->where('name', 'Business')->update(['is_active' => false]);
        Package::query()->where('name', 'Basic')->update(['is_qualifying' => false]);
        Rank::query()->where('name', 'Gold')->update(['bonus_amount' => 1_234_500]);
        BonusRule::query()->update(['is_active' => false]);
        BonusRule::query()->create(['type' => BonusRule::SALES, 'name' => 'Top seller', 'threshold' => 5_000_000, 'amount' => 200_000, 'is_active' => true]);
        CommissionRule::query()->where('key', CommissionRule::CAP_OVERFLOW_BEHAVIOR)->update(['value' => 'carry_forward']);

        $premium = Package::query()->where('name', 'Premium')->firstOrFail();

        $this->get(route('membership'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('packages', 3) // Business is off sale
                ->where('packages.0.name', 'Basic')
                ->where('packages.0.referralBonus', null) // not a qualifying package
                ->where('packages', fn ($packages) => collect($packages)->firstWhere('name', 'Premium')['referralBonus']
                    === Money::format(intdiv($premium->price * 500, 10_000)))
                ->where('ranks', fn ($ranks) => collect($ranks)->firstWhere('name', 'Gold')['bonus'] === '৳12,345.00')
                ->has('bonusRules', 1)
                ->where('bonusRules.0.name', 'Top seller')
                ->where('bonusRules.0.threshold', '৳50,000.00')
                ->where('bonusRules.0.amount', '৳2,000.00')
                ->where('rates.overCap', 'carry_forward'));
    }

    public function test_starter_sections_are_seeded_once()
    {
        $service = app(MembershipPageService::class);

        $service->seedDefaults();
        $count = MembershipSection::query()->count();
        $this->assertGreaterThan(0, $count);

        MembershipSection::query()->first()?->update(['title_en' => 'Edited by an admin']);
        $service->seedDefaults();

        $this->assertSame($count, MembershipSection::query()->count());
        $this->assertTrue(MembershipSection::query()->where('title_en', 'Edited by an admin')->exists());
    }
}
