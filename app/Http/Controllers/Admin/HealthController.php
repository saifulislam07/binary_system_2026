<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SystemHealth;
use Illuminate\Contracts\View\View;

class HealthController extends Controller
{
    public function __invoke(SystemHealth $health): View
    {
        return view('admin.health.index', ['checks' => $health->checks()]);
    }
}
