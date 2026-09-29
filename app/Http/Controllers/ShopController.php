<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Package;
use App\Models\Product;
use App\Services\ShopCatalog;
use App\Support\Money;
use App\Support\RichText;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public shop: browse products by category and brand, search and sort;
 * product pages list the packages each product is sold in (purchases go
 * through packages).
 */
class ShopController extends Controller
{
    private const SORTS = ['featured', 'price_asc', 'price_desc', 'newest'];

    public function index(Request $request, ShopCatalog $catalog): Response
    {
        $filters = $request->validate([
            'category' => ['nullable', 'string', 'max:120'],
            'brand' => ['nullable', 'string', 'max:120'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:'.implode(',', self::SORTS)],
            'deals' => ['nullable', 'boolean'],
        ]);

        $category = isset($filters['category'])
            ? Category::query()->where('slug', $filters['category'])->where('is_active', true)->first()
            : null;
        $brand = isset($filters['brand'])
            ? Brand::query()->with('media')->where('slug', $filters['brand'])->where('is_active', true)->first()
            : null;
        $sort = $filters['sort'] ?? 'featured';
        $term = trim((string) ($filters['q'] ?? ''));
        $deals = (bool) ($filters['deals'] ?? false);

        $products = $catalog->visibleProducts()
            ->with(ShopCatalog::CARD_RELATIONS)
            ->when($category, fn ($q) => $q->where('category_id', $category->id))
            ->when($brand, fn ($q) => $q->where('brand_id', $brand->id))
            ->when($deals, fn ($q) => $q->whereColumn('compare_at_price', '>', 'price'))
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', "%{$term}%"))))
            ->when($sort === 'featured', fn ($q) => $q->orderByDesc('is_featured')->orderBy('sort_order'))
            ->when($sort === 'price_asc', fn ($q) => $q->orderBy('price'))
            ->when($sort === 'price_desc', fn ($q) => $q->orderByDesc('price'))
            ->when($sort === 'newest', fn ($q) => $q->orderByDesc('id'))
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        return Inertia::render('shop/Index', [
            'products' => $products->through(fn (Product $p) => $catalog->card($p)),
            'categories' => $catalog->categories(),
            'brands' => $catalog->brands($category),
            'current' => $category === null ? null : ['slug' => $category->slug, 'name' => $category->name, 'nameBn' => $category->name_bn, 'description' => $category->description],
            'currentBrand' => $brand === null ? null : ['slug' => $brand->slug, 'name' => $brand->name, 'description' => $brand->description, 'logo' => $brand->logoUrl()],
            'filters' => ['q' => $term, 'sort' => $sort, 'deals' => $deals],
            'unknownCategory' => isset($filters['category']) && $category === null,
        ]);
    }

    public function show(Product $product, ShopCatalog $catalog): Response
    {
        $visible = $catalog->visibleProducts()->whereKey($product->id)->exists();
        abort_unless($visible, 404);

        $product->load(['media', 'category', 'brand.media', 'packages' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')]);

        $related = $catalog->visibleProducts()
            ->with(ShopCatalog::CARD_RELATIONS)
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->limit(4)
            ->get();

        $description = $product->descriptionHtml();

        return Inertia::render('shop/Show', [
            'product' => [
                ...$catalog->card($product),
                'sku' => $product->sku,
                'description' => $description,
                'summary' => Str::limit(RichText::toPlain($description), 160),
                'highlights' => $product->highlightList(),
                'categorySlug' => $product->category?->slug,
                'brandLogo' => $product->brand?->logoUrl(),
                'gallery' => $product->getMedia('images')->map(fn ($media) => [
                    'id' => $media->id,
                    'large' => $media->getUrl('large'),
                    'thumb' => $media->getUrl('card'),
                ])->values(),
            ],
            'packages' => $product->packages->map(fn (Package $package) => [
                'id' => $package->id,
                'name' => $package->name,
                'price' => Money::format($package->price),
                'quantity' => (int) $package->getRelationValue('pivot')->getAttribute('quantity'),
            ])->values(),
            'related' => $related->map(fn (Product $p) => $catalog->card($p))->values(),
        ]);
    }
}
