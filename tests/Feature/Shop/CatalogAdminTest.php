<?php

namespace Tests\Feature\Shop;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Package;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class CatalogAdminTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->admin = Admin::factory()->superAdmin()->create();
        $this->actingAs($this->admin, 'admin');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function productForm(array $overrides = []): array
    {
        return [
            'name' => 'Studio Headphones', 'sku' => 'aud-hp-9', 'brand' => 'Sonic', 'category_id' => '',
            'price' => '6500', 'compare_at_price' => '7900', 'description' => 'Closed-back headphones.',
            'highlights' => "30 hours battery\nBluetooth 5.3", 'is_active' => '1', 'is_featured' => '1', 'sort_order' => '1',
            ...$overrides,
        ];
    }

    public function test_an_admin_creates_a_category_and_a_product_with_photos()
    {
        $this->post(route('admin.categories.store'), [
            'name' => 'Audio', 'name_bn' => 'অডিও', 'description' => 'Sound', 'sort_order' => '1', 'is_active' => '1',
            'image' => UploadedFile::fake()->image('audio.jpg', 800, 600),
        ])->assertRedirect(route('admin.categories.index'));

        $category = Category::query()->where('slug', 'audio')->firstOrFail();
        $this->assertSame('অডিও', $category->name_bn);
        $this->assertNotNull($category->imageUrl());

        $this->post(route('admin.products.store'), $this->productForm([
            'category_id' => (string) $category->id,
            'images' => [UploadedFile::fake()->image('front.jpg', 1200, 900), UploadedFile::fake()->image('side.jpg', 1200, 900)],
        ]))->assertRedirect(route('admin.products.index'));

        $product = Product::query()->where('sku', 'AUD-HP-9')->firstOrFail();
        $this->assertSame('studio-headphones', $product->slug);
        $this->assertSame(650_000, $product->price);
        $this->assertSame(790_000, $product->compare_at_price);
        $this->assertSame(17, $product->discountPercent());
        $this->assertCount(2, $product->getMedia('images'));
        Storage::disk('public')->assertExists($product->getFirstMedia('images')?->getPathRelativeToRoot('card') ?? 'missing');

        $log = Activity::query()->where('description', 'Product created')->firstOrFail();
        $this->assertTrue($log->causer?->is($this->admin));
        $this->assertSame(2, $log->properties['attributes']['images_added']);
    }

    public function test_editing_a_product_can_remove_photos_and_is_audited()
    {
        $product = Product::factory()->create(['sku' => 'AUD-HP-9', 'price' => 650_000]);
        $product->addMedia(UploadedFile::fake()->image('a.jpg', 800, 600))->toMediaCollection('images');
        $keep = $product->addMedia(UploadedFile::fake()->image('b.jpg', 800, 600))->toMediaCollection('images');
        $drop = $product->getMedia('images')->first();

        $this->put(route('admin.products.update', $product), $this->productForm([
            'name' => $product->name, 'price' => '5999.50', 'compare_at_price' => '', 'remove_images' => [(string) $drop?->id],
        ]))->assertRedirect(route('admin.products.index'));

        $product->refresh();
        $this->assertSame(599_950, $product->price);
        $this->assertNull($product->compare_at_price);
        $this->assertSame([$keep->id], $product->getMedia('images')->pluck('id')->all());

        $log = Activity::query()->where('description', 'Product updated')->firstOrFail();
        $this->assertSame(650_000, $log->properties['old']['price']);
        $this->assertSame(599_950, $log->properties['attributes']['price']);
        $this->assertSame(1, $log->properties['attributes']['images_removed']);
    }

    public function test_product_input_is_validated()
    {
        Product::factory()->create(['sku' => 'TAKEN-1']);

        $this->post(route('admin.products.store'), $this->productForm([
            'sku' => 'taken-1', 'price' => '100', 'compare_at_price' => '90',
            'images' => [UploadedFile::fake()->image('tiny.jpg', 100, 100)],
        ]))->assertSessionHasErrors(['sku', 'compare_at_price', 'images.0']);

        $this->assertSame(1, Product::query()->count());
    }

    public function test_packages_list_what_is_inside()
    {
        $earbuds = Product::factory()->create(['name' => 'Earbuds']);
        $cable = Product::factory()->create(['name' => 'Cable']);
        $basic = Package::query()->where('name', 'Basic')->firstOrFail();
        $form = [
            'name' => 'Basic', 'price' => '1000', 'bv_value' => '1000', 'cost_of_goods' => '400',
            'is_qualifying' => '1', 'is_active' => '1', 'sort_order' => (string) $basic->sort_order,
        ];

        $this->get(route('admin.packages.edit', $basic))->assertOk()->assertSee('What\'s inside', false)->assertSee('Earbuds');

        $this->put(route('admin.packages.update', $basic), [...$form, 'products' => [$earbuds->id => '2', $cable->id => '']])
            ->assertSessionHasNoErrors();
        $this->assertSame([$earbuds->id => 2], $basic->products()->pluck('package_product.quantity', 'products.id')->map(fn ($q) => (int) $q)->all());

        $log = Activity::query()->where('description', 'Package updated')->latest('id')->firstOrFail();
        $this->assertSame([], $log->properties['old']['products']);
        $this->assertSame([$earbuds->id => 2], $log->properties['attributes']['products']);

        // Clearing every quantity empties the package; leaving the list out changes nothing.
        $this->put(route('admin.packages.update', $basic), [...$form, 'products' => [$earbuds->id => '']])->assertSessionHasNoErrors();
        $this->assertSame(0, $basic->products()->count());
    }

    public function test_only_catalog_managers_reach_the_catalog()
    {
        $this->get(route('admin.products.index'))->assertOk();
        $this->get(route('admin.products.create'))->assertOk();
        $this->get(route('admin.categories.index'))->assertOk();
        $this->get(route('admin.categories.create'))->assertOk();

        $support = Admin::factory()->create()->assignRole('support');
        $this->actingAs($support, 'admin')->get(route('admin.products.index'))->assertForbidden();
        $this->actingAs($support, 'admin')->post(route('admin.categories.store'), ['name' => 'X'])->assertForbidden();
    }
}
