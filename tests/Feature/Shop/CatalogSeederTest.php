<?php

namespace Tests\Feature\Shop;

use App\Models\Category;
use App\Models\Package;
use App\Models\Product;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_demo_catalog_seeds_products_with_photos_into_the_packages()
    {
        Storage::fake('public');

        $this->seed(CatalogSeeder::class);

        $this->assertSame(6, Category::query()->count());
        $this->assertSame(19, Product::query()->count());
        $this->assertSame(0, Product::query()->whereDoesntHave('media')->count(), 'Every product has a photo');
        $this->assertCount(2, Product::query()->where('sku', 'CM-DR-001')->firstOrFail()->getMedia('images'));

        foreach (Package::all() as $package) {
            $this->assertTrue($package->products()->exists(), "{$package->name} has products");
            $this->assertTrue($package->hasMedia('image'), "{$package->name} has a photo");
        }

        // Idempotent: a second run adds nothing.
        $this->seed(CatalogSeeder::class);
        $this->assertSame(19, Product::query()->count());
        $this->assertSame(22, Product::query()->withCount('media')->get()->sum('media_count'));
    }
}
