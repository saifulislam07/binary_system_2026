<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ConfigurationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePackageRequest;
use App\Models\Admin;
use App\Models\Package;
use App\Models\Product;
use App\Services\PackageAdminService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class PackageController extends Controller
{
    public function index(): View
    {
        return view('admin.packages.index', [
            'packages' => Package::query()->with('media')->withCount(['members', 'sales'])->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.packages.form', [
            'package' => new Package(['is_qualifying' => true, 'is_active' => true, 'sort_order' => Package::query()->max('sort_order') + 1]),
            'products' => $this->productChoices(),
            'included' => [],
        ]);
    }

    public function store(SavePackageRequest $request, PackageAdminService $packages): RedirectResponse
    {
        $package = $packages->create($request->packageData(), $this->admin($request), $this->image($request), $request->packageProducts());

        return redirect()->route('admin.packages.index')->with('success', "Package {$package->name} created.");
    }

    public function edit(Package $package): View
    {
        return view('admin.packages.form', [
            'package' => $package,
            'products' => $this->productChoices(),
            'included' => $package->products()->pluck('package_product.quantity', 'products.id')->all(),
        ]);
    }

    public function update(SavePackageRequest $request, Package $package, PackageAdminService $packages): RedirectResponse
    {
        try {
            $packages->update($package, $request->packageData(), $this->admin($request), $this->image($request), $request->boolean('remove_image'), $request->packageProducts());
        } catch (ConfigurationException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.packages.index')->with('success', "Package {$package->name} saved. New prices and BV apply to orders from now on.");
    }

    /**
     * @return Collection<string, \Illuminate\Database\Eloquent\Collection<int, Product>>
     */
    private function productChoices(): Collection
    {
        return Product::query()->with('category:id,name')->orderBy('name')->get()
            ->groupBy(fn (Product $product) => $product->category->name ?? 'Uncategorised');
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
