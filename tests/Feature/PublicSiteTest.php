<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Member;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
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
                ->where('packages.0.bv', '1,000.00')
                ->where('sponsorCode', null)
                ->where('canRegister', true));
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
