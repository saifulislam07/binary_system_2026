<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

/**
 * Public shop front: the packages on sale as products, and the way in. A
 * `?ref=MBR-…` sponsor code is carried through to registration.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $ref = $request->query('ref');
        $packages = Package::query()->with('media')->where('is_active', true)->orderBy('sort_order')->get();

        return Inertia::render('Welcome', [
            'packages' => $packages->map(fn (Package $package) => [
                'id' => $package->id,
                'name' => $package->name,
                'description' => $package->description,
                'price' => Money::format($package->price),
                'bv' => Money::format($package->bv_value, symbol: false),
                'qualifying' => $package->is_qualifying,
                'image' => $package->imageUrl(),
            ])->values(),
            'startingPrice' => $packages->isEmpty() ? null : Money::format((int) $packages->min('price')),
            'sponsorCode' => is_string($ref) && preg_match('/^[A-Za-z]{3}-\d{6,}$/', $ref) ? strtoupper($ref) : null,
            'canRegister' => Features::enabled(Features::registration()),
            'contact' => array_filter(
                (array) config('business.contact'),
                fn ($value) => is_string($value) && trim($value) !== '',
            ),
        ]);
    }
}
