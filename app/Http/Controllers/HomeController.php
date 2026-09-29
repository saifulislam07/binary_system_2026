<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Product;
use App\Services\ShopCatalog;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public shop front, product-first: categories, deals, featured products,
 * brands and the packages on sale (with what's inside each). Membership and
 * commission details live on the membership page (linked from the footer
 * and from registration). A `?ref=MBR-…` sponsor code is carried through to
 * registration. Header and footer data come from the shared `shop` prop
 * (ShopCatalog::navigation()).
 */
class HomeController extends Controller
{
    public function __invoke(Request $request, ShopCatalog $catalog): Response
    {
        $ref = $request->query('ref');
        $packages = Package::query()->with(['media', 'products' => fn ($q) => $q->orderBy('sort_order')])->where('is_active', true)->orderBy('sort_order')->get();

        return Inertia::render('Welcome', [
            'categories' => $catalog->categories(),
            'featured' => $catalog->visibleProducts()
                ->with(ShopCatalog::CARD_RELATIONS)
                ->where('is_featured', true)
                ->orderBy('sort_order')
                ->limit(8)
                ->get()
                ->map(fn (Product $p) => $catalog->card($p))
                ->values(),
            'deals' => $catalog->visibleProducts()
                ->with(ShopCatalog::CARD_RELATIONS)
                ->whereColumn('compare_at_price', '>', 'price')
                ->orderByRaw('(compare_at_price - price) / compare_at_price DESC')
                ->limit(4)
                ->get()
                ->map(fn (Product $p) => $catalog->card($p))
                ->values(),
            'brands' => $catalog->brands(),
            'packages' => $packages->map(fn (Package $package) => [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'price' => Money::format($package->price),
                'image' => $package->imageUrl(),
                'items' => $package->products->map(fn (Product $product) => [
                    'name' => $product->name,
                    'quantity' => (int) $product->getRelationValue('pivot')->getAttribute('quantity'),
                ])->values(),
            ])->values(),
            'startingPrice' => $packages->isEmpty() ? null : Money::format((int) $packages->min('price')),
            'sponsorCode' => is_string($ref) && preg_match('/^[A-Za-z]{3}-\d{6,}$/', $ref) ? strtoupper($ref) : null,
        ]);
    }
}
