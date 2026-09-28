<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProductRequest;
use App\Models\Admin;
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
            'status' => ['nullable', 'in:active,inactive,featured'],
        ]);

        $products = Product::query()
            ->with(['media', 'category:id,name'])
            ->withCount('packages')
            ->when($filters['q'] ?? null, fn ($q, string $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('brand', 'like', "%{$term}%")))
            ->when($filters['category'] ?? null, fn ($q, int $id) => $q->where('category_id', $id))
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
            'filters' => $filters,
        ]);
    }

    public function create(): View
    {
        return view('admin.catalog.products.form', [
            'product' => new Product(['is_active' => true, 'is_featured' => false, 'sort_order' => 0]),
            'categories' => Category::query()->orderBy('sort_order')->pluck('name', 'id'),
        ]);
    }

    public function store(SaveProductRequest $request, CatalogAdminService $catalog): RedirectResponse
    {
        $product = $catalog->saveProduct(null, $request->productData(), $this->admin($request), $request->newImages());

        return redirect()->route('admin.products.index')->with('success', "Product {$product->name} created.");
    }

    public function edit(Product $product): View
    {
        return view('admin.catalog.products.form', [
            'product' => $product->load('media', 'packages:id,name'),
            'categories' => Category::query()->orderBy('sort_order')->pluck('name', 'id'),
        ]);
    }

    public function update(SaveProductRequest $request, Product $product, CatalogAdminService $catalog): RedirectResponse
    {
        $catalog->saveProduct($product, $request->productData(), $this->admin($request), $request->newImages(), $request->removeImageIds());

        return redirect()->route('admin.products.index')->with('success', "Product {$product->name} saved.");
    }

    private function admin(Request $request): Admin
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin, 403);

        return $admin;
    }
}
