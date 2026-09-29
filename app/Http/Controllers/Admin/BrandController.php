<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveBrandRequest;
use App\Models\Admin;
use App\Models\Brand;
use App\Services\CatalogAdminService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class BrandController extends Controller
{
    public function index(): View
    {
        return view('admin.catalog.brands.index', [
            'brands' => Brand::query()->with('media')->withCount('products')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.catalog.brands.form', ['brand' => new Brand(['is_active' => true, 'sort_order' => (int) Brand::query()->max('sort_order') + 1])]);
    }

    public function store(SaveBrandRequest $request, CatalogAdminService $catalog): RedirectResponse
    {
        $brand = $catalog->saveBrand(null, $request->brandData(), $this->admin($request), $this->logo($request));

        return redirect()->route('admin.brands.index')->with('success', "Brand {$brand->name} created.");
    }

    public function edit(Brand $brand): View
    {
        return view('admin.catalog.brands.form', ['brand' => $brand]);
    }

    public function update(SaveBrandRequest $request, Brand $brand, CatalogAdminService $catalog): RedirectResponse
    {
        $catalog->saveBrand($brand, $request->brandData(), $this->admin($request), $this->logo($request), $request->boolean('remove_logo'));

        return redirect()->route('admin.brands.index')->with('success', "Brand {$brand->name} saved.");
    }

    private function logo(Request $request): ?UploadedFile
    {
        $file = $request->file('logo');

        return $file instanceof UploadedFile ? $file : null;
    }

    private function admin(Request $request): Admin
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin, 403);

        return $admin;
    }
}
