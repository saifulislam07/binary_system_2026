<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CommissionRule;
use App\Models\Member;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_landing_page_lists_the_packages_on_sale()
    {
        Package::query()->where('name', 'Business')->update(['is_active' => false]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->has('packages', 3)
                ->where('packages.0.name', 'Basic')
                ->where('packages.0.price', '৳1,000.00')
                ->missing('packages.0.bv') // the shop front is product-first: no BV or commission talk
                ->where('packages.0.image', null)
                ->where('startingPrice', '৳1,000.00')
                ->where('sponsorCode', null)
                ->where('canRegister', true)
                ->where('contact', []));
    }

    public function test_the_membership_page_discloses_the_rules_with_the_live_rates()
    {
        $this->get(route('membership'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('membership/Index')
                ->where('rates.referral', '5%')
                ->where('rates.binary', '10%')
                ->where('rates.dailyCap', '৳5,000.00')
                ->where('rates.weeklyCap', '৳20,000.00')
                ->where('rates.monthlyCap', '৳50,000.00')
                ->where('rates.minWithdrawal', '৳1,000.00')
                ->where('ranks', ['Member', 'Bronze', 'Silver', 'Gold', 'Platinum', 'Diamond']));

        // An admin rate change shows up at once.
        CommissionRule::query()->where('key', CommissionRule::REFERRAL_RATE_BPS)->update(['value' => '750']);
        $this->get(route('membership'))->assertInertia(fn (Assert $page) => $page->where('rates.referral', '7.5%'));
    }

    public function test_an_admin_uploaded_photo_appears_on_the_shop_card()
    {
        Storage::fake('public');
        $admin = Admin::factory()->superAdmin()->create();
        $premium = Package::query()->where('name', 'Premium')->firstOrFail();
        $form = [
            'name' => 'Premium', 'price' => '10000', 'bv_value' => '10000', 'cost_of_goods' => '4000',
            'is_qualifying' => '1', 'is_active' => '1', 'sort_order' => (string) $premium->sort_order,
        ];

        $this->actingAs($admin, 'admin')
            ->put(route('admin.packages.update', $premium), [...$form, 'image' => UploadedFile::fake()->image('premium.jpg', 1200, 900)])
            ->assertSessionHasNoErrors();

        $media = $premium->fresh()?->getFirstMedia('image');
        $this->assertNotNull($media);
        Storage::disk('public')->assertExists($media->getPathRelativeToRoot('card'));
        $this->assertSame('replaced', Activity::query()->where('description', 'Package updated')->latest('id')->firstOrFail()->properties['attributes']['image']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('packages.2.name', 'Premium')
            ->where('packages.2.image', fn (string $url) => str_contains($url, 'premium-card')));

        // Too small, or not an image: refused.
        $this->put(route('admin.packages.update', $premium), [...$form, 'image' => UploadedFile::fake()->image('tiny.jpg', 100, 100)])
            ->assertSessionHasErrors('image');
        $this->put(route('admin.packages.update', $premium), [...$form, 'image' => UploadedFile::fake()->create('menu.pdf', 50, 'application/pdf')])
            ->assertSessionHasErrors('image');

        $this->put(route('admin.packages.update', $premium), [...$form, 'remove_image' => '1'])->assertSessionHasNoErrors();
        $this->assertFalse($premium->fresh()?->hasMedia('image'));
    }

    public function test_buy_now_preselects_the_package_at_sign_up_and_checkout()
    {
        $premium = Package::query()->where('name', 'Premium')->firstOrFail();

        $this->get(route('register', ['package' => $premium->id, 'ref' => 'MBR-100001']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedPackageId', $premium->id)
                ->where('sponsorCode', 'MBR-100001'));

        $premium->update(['is_active' => false]);
        $this->get(route('register', ['package' => $premium->id]))
            ->assertInertia(fn (Assert $page) => $page->where('selectedPackageId', null));

        $basic = Package::query()->where('name', 'Basic')->firstOrFail();
        $standard = Package::query()->where('name', 'Standard')->firstOrFail();
        $member = Member::factory()->create(['package_id' => $basic->id]);

        $this->actingAs($member->user)
            ->get(route('checkout.index', ['package' => $standard->id]))
            ->assertInertia(fn (Assert $page) => $page->where('selectedPackageId', $standard->id));

        $this->actingAs($member->user)
            ->get(route('checkout.index', ['package' => 999999]))
            ->assertInertia(fn (Assert $page) => $page->where('selectedPackageId', $basic->id));
    }

    public function test_contact_details_show_only_when_configured()
    {
        config(['business.contact' => ['phone' => '+8801711000000', 'email' => 'help@example.com', 'address' => '  ', 'hours' => null]]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('contact', ['phone' => '+8801711000000', 'email' => 'help@example.com']));
    }

    public function test_a_referral_link_to_the_home_page_carries_the_sponsor_code()
    {
        $this->get('/?ref=mbr-100001')
            ->assertInertia(fn (Assert $page) => $page->where('sponsorCode', 'MBR-100001'));

        $this->get('/?ref=<script>')
            ->assertInertia(fn (Assert $page) => $page->where('sponsorCode', null));
    }

    public function test_error_pages_are_bilingual_and_lead_home()
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertSee('Page not found')
            ->assertSee('পাতাটি পাওয়া যায়নি')
            ->assertSee('href="'.url('/').'"', false);

        $support = Admin::factory()->create()->assignRole('support');
        $this->actingAs($support, 'admin')
            ->get(route('admin.settings.index'))
            ->assertForbidden()
            ->assertSee('এই পাতায় আপনার প্রবেশাধিকার নেই')
            ->assertSee('href="'.url('/admin').'"', false);
    }

    public function test_the_maintenance_page_has_no_link_away()
    {
        $html = view('errors.503')->render();

        $this->assertStringContainsString('কিছুক্ষণের মধ্যে ফিরে আসছি', $html);
        $this->assertStringNotContainsString('Go back home', $html);
    }

    public function test_a_failed_page_visit_in_the_member_app_reloads_to_the_error_page()
    {
        config(['app.debug' => false]);
        $member = Member::factory()->create(); // pending: team pages are off-limits

        $this->actingAs($member->user)
            ->get('/no-such-page', ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', url('/no-such-page'));
    }

    public function test_a_failed_form_submission_goes_back_with_a_message()
    {
        config(['app.debug' => false]);
        Route::middleware('web')->post('/__test/forbidden', fn () => abort(403));
        Route::middleware('web')->post('/__test/expired', fn () => throw new TokenMismatchException);

        $this->from('/dashboard')
            ->post('/__test/forbidden', [], ['X-Inertia' => 'true'])
            ->assertRedirect('/dashboard');

        $this->from('/dashboard')
            ->post('/__test/expired', [], ['X-Inertia' => 'true'])
            ->assertRedirect('/dashboard');
    }

    public function test_debug_mode_keeps_the_real_error_for_developers()
    {
        config(['app.debug' => true]);

        $this->get('/no-such-page', ['X-Inertia' => 'true'])->assertNotFound();
    }
}
