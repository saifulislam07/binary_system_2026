<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Stand-in page for admin sections whose screens arrive in later phases.
 * The route (and its permission gate) is real so navigation and access
 * control can be built and tested now. Replace the route when the section
 * lands.
 */
class SectionPlaceholderController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('admin.placeholder', [
            'section' => (string) $request->route()?->defaults['section'],
            'phase' => (int) $request->route()?->defaults['phase'],
        ]);
    }
}
