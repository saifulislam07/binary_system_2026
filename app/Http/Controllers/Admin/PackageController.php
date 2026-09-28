<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ConfigurationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePackageRequest;
use App\Models\Admin;
use App\Models\Package;
use App\Services\PackageAdminService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function index(): View
    {
        return view('admin.packages.index', [
            'packages' => Package::query()->withCount(['members', 'sales'])->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.packages.form', ['package' => new Package(['is_qualifying' => true, 'is_active' => true, 'sort_order' => Package::query()->max('sort_order') + 1])]);
    }

    public function store(SavePackageRequest $request, PackageAdminService $packages): RedirectResponse
    {
        $package = $packages->create($request->packageData(), $this->admin($request));

        return redirect()->route('admin.packages.index')->with('success', "Package {$package->name} created.");
    }

    public function edit(Package $package): View
    {
        return view('admin.packages.form', ['package' => $package]);
    }

    public function update(SavePackageRequest $request, Package $package, PackageAdminService $packages): RedirectResponse
    {
        try {
            $packages->update($package, $request->packageData(), $this->admin($request));
        } catch (ConfigurationException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.packages.index')->with('success', "Package {$package->name} saved. New prices and BV apply to orders from now on.");
    }

    private function admin(Request $request): Admin
    {
        $admin = $request->user('admin');
        abort_unless($admin instanceof Admin, 403);

        return $admin;
    }
}
