<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AdminDashboardService $dashboard): View
    {
        $period = $request->query('period');
        $period = is_string($period) && array_key_exists($period, AdminDashboardService::PERIODS) ? $period : 'month';

        return view('admin.dashboard', [
            'period' => $period,
            'periods' => AdminDashboardService::PERIODS,
            'm' => $dashboard->metrics($period),
        ]);
    }
}
