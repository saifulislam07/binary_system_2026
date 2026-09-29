<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProductRequest;
use App\Models\Admin;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogAdminService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'integer'],
            'brand' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:active,inactive,featured'],
        ]);

        $products = Product::query()
            ->with(['media', 'category:id,name', 'brand:id,name'])
            ->withCount('packages')
            ->when($filters['q'] ?? null, fn ($q, string $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', "%{$term}%"))))
            ->when($filters['category'] ?? null, fn ($q, int $id) => $q->where('category_id', $id))
            ->when($filters['brand'] ?? null, fn ($q, int $id) => $q->where('brand_id', $id))
            ->when(($filters['status'] ?? null) === 'active', fn ($q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when(($filters['status'] ?? null) === 'featured', fn ($q) => $q->where('is_featured', true))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('admin.catalog.products.index', [
            'products' => $products,
            'categories' => Category::query()->orderBy('sort_order')->pluck('name', 'id'),
            'brands' => Brand::query()->orderBy('name')->pluck('name', 'id'),
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.catalog.products.form', [
            'product' => new Product(['is_active' => true, 'is_featured' => false, 'sort_order' => 0]),
            ...$this->choices(),
        ]);
    }

    public function store(SaveProductRequest $request, CatalogAdminService $catalog): RedirectResponse
    {
        $admin = $this->admin($request);
        $product = $catalog->saveProduct(null, $this->withBrand($request, $catalog, $admin), $admin, $request->newImages(), [], $request->imageOrder());

        return redirect()->route('admin.products.index')->with('success', "Product {$product->name} created.");
    }

    public function edit(Product $product): View
    {
        return view('admin.catalog.products.form', [
            'product' => $product->load('media', 'packages:id,name'),
            ...$this->choices(),
        ]);
    }

    public function update(SaveProductRequest $request, Product $product, CatalogAdminService $catalog): RedirectResponse
    {
        $admin = $this->admin($request);
        $catalog->saveProduct($product, $this->withBrand($request, $catalog, $admin), $admin, $request->newImages(), $request->removeImageIds(), $request->imageOrder());

        return redirect()->route('admin.products.index')->with('success', "Product {$product->name} saved.");
    }

    /**
     * @return array{categories: mixed, brands: mixed}
     */
    private function choices(): array
    {
        return [
            'categories' => Category::query()->orderBy('sort_order')->pluck('name', 'id'),
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name', 'is_active']),
        ];
    }

    /**
     * The product fields, with a brand typed on the form created first.
     *
     * @return array{category_id: int|null, brand_id: int|null, name: string, sku: string, description: string|null, highlights: string|null, price: int, compare_at_price: int|null, is_active: bool, is_featured: bool, sort_order: int}
     */
    private function withBrand(SaveProductRequest $request, CatalogAdminService $catalog, Admin $admin): array
    {
        $data = $request->productData();
        $newBrand = $request->newBrandName();

        if ($newBrand !== null) {
            $data['brand_id'] = $catalog->brandNamed($newBrand, $admin)->id;
        }

        return $data;
    }

    private function admin(Request $request): Admin
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin, 403);

        return $admin;
    }
}
