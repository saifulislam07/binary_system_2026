<?php

namespace Tests\Feature\Shop;

use App\Models\Category;
use App\Models\Member;
use App\Models\Package;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShopTest extends TestCase
{
    use RefreshDatabase;

    private Category $audio;

    private Category $hidden;

    protected function setUp(): void
    {
        parent::setUp();

        $this->audio = Category::query()->create(['name' => 'Audio', 'name_bn' => 'অডিও', 'sort_order' => 1]);
        $this->hidden = Category::query()->create(['name' => 'Retired', 'sort_order' => 2, 'is_active' => false]);
    }

    private function product(array $attributes = []): Product
    {
        return Product::factory()->create(['category_id' => $this->audio->id, ...$attributes]);
    }

    public function test_the_shop_lists_only_what_is_on_sale()
    {
        $shown = $this->product(['name' => 'Studio Headphones', 'price' => 650_000, 'compare_at_price' => 790_000]);
        $this->product(['name' => 'Old Radio', 'is_active' => false]);
        Product::factory()->create(['name' => 'Retired Player', 'category_id' => $this->hidden->id]);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('shop/Index')
                ->has('products.data', 1)
                ->where('products.data.0.slug', $shown->slug)
                ->where('products.data.0.price', '৳6,500.00')
                ->where('products.data.0.compareAt', '৳7,900.00')
                ->where('products.data.0.discount', 17)
                ->where('products.data.0.category', 'Audio')
                ->has('categories', 1)
                ->where('categories.0.count', 1)
                ->where('shop.categories.0.slug', 'audio'));
    }

    public function test_filter_by_category_search_and_sort()
    {
        $wearables = Category::query()->create(['name' => 'Wearables', 'sort_order' => 3]);
        $this->product(['name' => 'Bluetooth Speaker', 'price' => 185_000]);
        $this->product(['name' => 'Wireless Earbuds', 'price' => 245_000, 'brand' => 'Sonic']);
        Product::factory()->create(['name' => 'Fitness Band', 'price' => 165_000, 'category_id' => $wearables->id]);

        $this->get(route('shop.index', ['category' => 'audio', 'sort' => 'price_desc']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 2)
                ->where('products.data.0.name', 'Wireless Earbuds')
                ->where('current.slug', 'audio')
                ->where('current.nameBn', 'অডিও'));

        $this->get(route('shop.index', ['q' => 'sonic']))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.name', 'Wireless Earbuds'));

        $this->get(route('shop.index', ['sort' => 'price_asc']))
            ->assertInertia(fn (Assert $page) => $page->where('products.data.0.name', 'Fitness Band'));

        $this->get(route('shop.index', ['category' => 'retired']))
            ->assertInertia(fn (Assert $page) => $page->where('unknownCategory', true)->where('current', null)->has('products.data', 3));

        $this->get(route('shop.index', ['sort' => 'cheapest']))->assertSessionHasErrors('sort');
    }

    public function test_the_product_page_shows_the_packages_that_sell_it()
    {
        $product = $this->product(['name' => 'Studio Headphones', 'highlights' => "30 hours battery\n\nBluetooth 5.3\n"]);
        $related = $this->product(['name' => 'Wireless Earbuds']);
        $premium = Package::query()->where('name', 'Premium')->firstOrFail();
        $business = Package::query()->where('name', 'Business')->firstOrFail();
        $premium->products()->attach($product->id, ['quantity' => 2]);
        $business->products()->attach($product->id, ['quantity' => 1]);
        $business->update(['is_active' => false]);

        $this->get(route('shop.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('shop/Show')
                ->where('product.name', 'Studio Headphones')
                ->where('product.highlights', ['30 hours battery', 'Bluetooth 5.3'])
                ->where('product.categorySlug', 'audio')
                ->has('packages', 1) // the inactive package isn't offered
                ->where('packages.0.name', 'Premium')
                ->where('packages.0.price', '৳10,000.00')
                ->where('packages.0.quantity', 2)
                ->has('related', 1)
                ->where('related.0.slug', $related->slug));
    }

    public function test_hidden_products_have_no_page()
    {
        $inactive = $this->product(['is_active' => false]);
        $inHiddenCategory = Product::factory()->create(['category_id' => $this->hidden->id]);

        $this->get(route('shop.show', $inactive))->assertNotFound();
        $this->get(route('shop.show', $inHiddenCategory))->assertNotFound();
        $this->get('/shop/no-such-product')->assertNotFound();
    }

    public function test_the_home_page_shows_categories_and_featured_products()
    {
        $this->product(['name' => 'Featured Speaker', 'is_featured' => true]);
        $this->product(['name' => 'Plain Cable', 'is_featured' => false]);

        $this->get('/')
            ->assertInertia(fn (Assert $page) => $page
                ->has('categories', 1)
                ->where('categories.0.name', 'Audio')
                ->where('categories.0.count', 2)
                ->has('featured', 1)
                ->where('featured.0.name', 'Featured Speaker'));
    }

    public function test_shop_navigation_is_only_shared_with_shop_pages()
    {
        $this->product();
        $member = Member::factory()->active()->create();

        $this->get(route('membership'))->assertInertia(fn (Assert $page) => $page->where('shop.categories.0.name', 'Audio'));

        $this->actingAs($member->user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('shop', null));
    }
}
