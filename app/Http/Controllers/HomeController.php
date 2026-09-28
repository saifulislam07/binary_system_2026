<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Support\Money;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

/**
 * Public landing page: what the business is, the packages on sale, and the
 * way in. A `?ref=MBR-…` sponsor code is carried through to registration.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $ref = $request->query('ref');

        return Inertia::render('Welcome', [
            'packages' => Package::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'description', 'price', 'bv_value', 'is_qualifying'])
                ->map(fn (Package $package) => [
                    'id' => $package->id,
                    'name' => $package->name,
                    'description' => $package->description,
                    'price' => Money::format($package->price),
                    'bv' => Money::format($package->bv_value, symbol: false),
                    'qualifying' => $package->is_qualifying,
                ])
                ->values(),
            'sponsorCode' => is_string($ref) && preg_match('/^[A-Za-z]{3}-\d{6,}$/', $ref) ? strtoupper($ref) : null,
            'canRegister' => Features::enabled(Features::registration()),
        ]);
    }
}
