<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Member;
use App\Models\Product;
use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The language switcher (বাংলা / English) for the shop, sign-in pages and
 * the member app. Tests run with MEMBER_LOCALE=en; these set it to Bangla
 * where the default matters.
 */
class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitors_start_in_bangla_and_can_switch()
    {
        config(['business.default_locale' => 'bn']);

        $this->get('/')
            ->assertSee('<html lang="bn"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'bn')
                ->where('locales', ['bn' => 'বাংলা', 'en' => 'English']));

        $this->from('/shop')
            ->post(route('locale.update'), ['locale' => 'en'])
            ->assertRedirect('/shop');

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
    }

    public function test_a_members_choice_is_saved_and_used_on_the_next_visit()
    {
        $member = Member::factory()->active()->create();
        $user = $member->user;

        $this->actingAs($user)
            ->post(route('locale.update'), ['locale' => 'bn'])
            ->assertRedirect();
        $this->assertSame('bn', $user->refresh()->locale);

        // A fresh session (another device) follows the saved choice.
        $this->flushSession();
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'bn'));
    }

    public function test_only_supported_languages_are_accepted()
    {
        $this->post(route('locale.update'), ['locale' => 'fr'])->assertSessionHasErrors('locale');
        $this->post(route('locale.update'), [])->assertSessionHasErrors('locale');
        $this->assertNull(session('locale'));
    }

    public function test_the_admin_panel_stays_in_english()
    {
        $this->withSession(['locale' => 'bn'])
            ->actingAs(Admin::factory()->superAdmin()->create(), 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Dashboard');

        $this->assertSame('en', app()->getLocale());
    }

    public function test_notifications_are_written_in_the_members_language()
    {
        $user = User::factory()->create(['locale' => 'bn']);
        $user->notify(new PasswordChanged);

        $this->assertSame('আপনার পাসওয়ার্ড বদলানো হয়েছে', $user->notifications()->firstOrFail()->data['title']);

        $english = User::factory()->create(['locale' => 'en']);
        $english->notify(new PasswordChanged);

        $this->assertSame('Your password was changed', $english->notifications()->firstOrFail()->data['title']);
    }

    public function test_validation_messages_follow_the_language()
    {
        $this->withSession(['locale' => 'bn'])
            ->post(route('register.store'), [])
            ->assertSessionHasErrors(['name' => 'নাম দিতে হবে।']);
    }

    public function test_shop_category_names_follow_the_language()
    {
        $audio = Category::query()->create(['name' => 'Audio', 'name_bn' => 'অডিও']);
        Product::factory()->create(['category_id' => $audio->id]);

        $this->withSession(['locale' => 'bn'])
            ->get(route('shop.index'))
            ->assertInertia(fn (Assert $page) => $page->where('products.data.0.category', 'অডিও'));

        $this->withSession(['locale' => 'en'])
            ->get(route('shop.index'))
            ->assertInertia(fn (Assert $page) => $page->where('products.data.0.category', 'Audio'));
    }
}
