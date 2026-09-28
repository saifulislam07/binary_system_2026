<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveCategoryRequest;
use App\Models\Admin;
use App\Models\Category;
use App\Services\CatalogAdminService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.catalog.categories.index', [
            'categories' => Category::query()->with('media')->withCount('products')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.catalog.categories.form', ['category' => new Category(['is_active' => true, 'sort_order' => (int) Category::query()->max('sort_order') + 1])]);
    }

    public function store(SaveCategoryRequest $request, CatalogAdminService $catalog): RedirectResponse
    {
        $category = $catalog->saveCategory(null, $request->categoryData(), $this->admin($request), $this->image($request));

        return redirect()->route('admin.categories.index')->with('success', "Category {$category->name} created.");
    }

    public function edit(Category $category): View
    {
        return view('admin.catalog.categories.form', ['category' => $category]);
    }

    public function update(SaveCategoryRequest $request, Category $category, CatalogAdminService $catalog): RedirectResponse
    {
        $catalog->saveCategory($category, $request->categoryData(), $this->admin($request), $this->image($request), $request->boolean('remove_image'));

        return redirect()->route('admin.categories.index')->with('success', "Category {$category->name} saved.");
    }

    private function image(Request $request): ?UploadedFile
    {
        $file = $request->file('image');

        return $file instanceof UploadedFile ? $file : null;
    }

    private function admin(Request $request): Admin
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin, 403);

        return $admin;
    }
}
