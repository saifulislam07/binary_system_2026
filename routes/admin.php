<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PendingMemberController;
use App\Http\Controllers\Admin\SectionPlaceholderController;
use Illuminate\Support\Facades\Route;

/*
| Admin panel routes. Loaded from bootstrap/app.php with the `web`
| middleware group, the `admin` URL prefix, the `admin.` name prefix and
| the UseAdminGuard middleware already applied.
|
| Every section is gated by its admin permission (see App\Enums\AdminPermission)
| — keep route gates and the menu 'can' keys in config/adminlte.php in sync.
*/

Route::middleware('guest:admin')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth:admin')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::redirect('/', '/admin/dashboard');
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('can:manage-members')->group(function () {
        Route::get('members', SectionPlaceholderController::class)->defaults('section', 'Members')->defaults('phase', 10)->name('members.index');
        // Manual activation override (normally the payment callback activates members).
        Route::get('members/pending', [PendingMemberController::class, 'index'])->name('members.pending');
        Route::post('members/{member}/activate', [PendingMemberController::class, 'activate'])->name('members.activate');
    });

    Route::middleware('can:manage-tree')->group(function () {
        Route::get('tree', SectionPlaceholderController::class)->defaults('section', 'Binary Tree')->defaults('phase', 10)->name('tree.index');
    });

    Route::middleware('can:manage-sales')->group(function () {
        Route::get('sales', SectionPlaceholderController::class)->defaults('section', 'Sales')->defaults('phase', 10)->name('sales.index');
    });

    Route::middleware('can:manage-withdrawals')->group(function () {
        Route::get('withdrawals', SectionPlaceholderController::class)->defaults('section', 'Withdrawals')->defaults('phase', 10)->name('withdrawals.index');
    });

    Route::middleware('can:manage-kyc')->group(function () {
        Route::get('kyc', SectionPlaceholderController::class)->defaults('section', 'KYC Review')->defaults('phase', 12)->name('kyc.index');
    });

    Route::middleware('can:view-reports')->group(function () {
        Route::get('financial', SectionPlaceholderController::class)->defaults('section', 'Financial')->defaults('phase', 10)->name('financial.index');
        Route::get('reports', SectionPlaceholderController::class)->defaults('section', 'Reports')->defaults('phase', 10)->name('reports.index');
    });

    Route::middleware('can:manage-settings')->group(function () {
        Route::get('settings', SectionPlaceholderController::class)->defaults('section', 'Settings')->defaults('phase', 10)->name('settings.index');
    });
});
