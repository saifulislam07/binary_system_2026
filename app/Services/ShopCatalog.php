<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Fortify\Features;

/**
 * What the public shop shows: only active products in active (or no)
 * categories, presented the same way everywhere.
 */
class ShopCatalog
{
    /** Relations card() reads — eager load them on every product list. */
    public const CARD_RELATIONS = ['media', 'category:id,name', 'brand:id,name,slug'];

    /**
     * @return Builder<Product>
     */
    public function visibleProducts(): Builder
    {
        return Product::query()
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('category_id')
                ->orWhereHas('category', fn (Builder $c) => $c->where('is_active', true)));
    }

    /**
     * @return array{id: int, slug: string, name: string, brand: string|null, brandSlug: string|null, category: string|null, price: string, compareAt: string|null, discount: int|null, image: string|null}
     */
    public function card(Product $product): array
    {
        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'brand' => $product->brand?->name,
            'brandSlug' => $product->brand?->slug,
            'category' => $product->category?->name,
            'price' => Money::format($product->price),
            'compareAt' => $product->discountPercent() === null ? null : Money::format((int) $product->compare_at_price),
            'discount' => $product->discountPercent(),
            'image' => $product->imageUrl(),
        ];
    }

    /**
     * Active categories that have something to show, with a tile image (the
     * category's own, else its first product photo).
     *
     * @return list<array{slug: string, name: string, nameBn: string|null, count: int, image: string|null}>
     */
    public function categories(): array
    {
        $categories = Category::query()
            ->with('media')
            ->where('is_active', true)
            ->withCount(['products' => fn (Builder $q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (Category $c) => $c->products_count > 0);

        $covers = Product::query()
            ->with('media')
            ->where('is_active', true)
            ->whereIn('category_id', $categories->pluck('id'))
            ->orderBy('sort_order')
            ->get()
            ->unique('category_id')
            ->keyBy('category_id');

        return array_values($categories->map(fn (Category $c) => [
            'slug' => $c->slug,
            'name' => $c->name,
            'nameBn' => $c->name_bn,
            'count' => (int) $c->products_count,
            'image' => $c->imageUrl() ?? $covers->get($c->id)?->imageUrl(),
        ])->all());
    }

    /**
     * Active brands with at least one visible product, optionally only
     * those in one category.
     *
     * @return list<array{slug: string, name: string, count: int, logo: string|null}>
     */
    public function brands(?Category $category = null): array
    {
        $visible = fn ($q) => $q->whereIn('products.id', $this->visibleProducts()
            ->when($category, fn ($p) => $p->where('category_id', $category->id))
            ->select('products.id'));

        return array_values(Brand::query()
            ->with('media')
            ->where('is_active', true)
            ->whereHas('products', $visible)
            ->withCount(['products' => $visible])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Brand $b) => [
                'slug' => $b->slug,
                'name' => $b->name,
                'count' => (int) $b->products_count,
                'logo' => $b->logoUrl(),
            ])
            ->all());
    }

    /**
     * Shared by every shop page's header and footer.
     *
     * @return array{categories: list<array{slug: string, name: string, nameBn: string|null}>, contact: array<string, string>, canRegister: bool}
     */
    public function navigation(): array
    {
        return [
            'categories' => array_values(Category::query()
                ->where('is_active', true)
                ->whereHas('products', fn (Builder $q) => $q->where('is_active', true))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['slug', 'name', 'name_bn'])
                ->map(fn (Category $c) => ['slug' => $c->slug, 'name' => $c->name, 'nameBn' => $c->name_bn])
                ->all()),
            'contact' => array_filter(
                array_map(fn ($v) => is_string($v) ? trim($v) : '', (array) config('business.contact')),
                fn (string $value) => $value !== '',
            ),
            'canRegister' => Features::enabled(Features::registration()),
        ];
    }
}
