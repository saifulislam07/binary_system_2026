<?php

namespace App\Services;

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
     * @return array{id: int, slug: string, name: string, brand: string|null, category: string|null, price: string, compareAt: string|null, discount: int|null, image: string|null}
     */
    public function card(Product $product): array
    {
        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'brand' => $product->brand,
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
