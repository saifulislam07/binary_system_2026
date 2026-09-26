<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\BonusController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FinancialController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\PendingMemberController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\SectionPlaceholderController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TreeController;
use App\Http\Controllers\Admin\WithdrawalController;
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
        Route::get('members', [MemberController::class, 'index'])->name('members.index');
        // Manual activation override (normally the payment callback activates members).
        Route::get('members/pending', [PendingMemberController::class, 'index'])->name('members.pending');
        Route::post('members/{member}/activate', [PendingMemberController::class, 'activate'])->name('members.activate');
        Route::get('members/{member}', [MemberController::class, 'show'])->name('members.show');
        Route::get('members/{member}/edit', [MemberController::class, 'edit'])->name('members.edit');
        Route::put('members/{member}', [MemberController::class, 'update'])->name('members.update');
        Route::post('members/{member}/suspend', [MemberController::class, 'suspend'])->name('members.suspend');
        Route::post('members/{member}/reinstate', [MemberController::class, 'reinstate'])->name('members.reinstate');
        Route::post('members/{member}/package', [MemberController::class, 'changePackage'])->name('members.package');
    });

    Route::middleware('can:manage-tree')->group(function () {
        Route::get('tree', [TreeController::class, 'index'])->name('tree.index');
        Route::get('tree/node/{member:member_code}', [TreeController::class, 'node'])->name('tree.node');
        Route::post('tree/adjust/{member:member_code}', [TreeController::class, 'adjust'])->name('tree.adjust');
    });

    Route::middleware('can:manage-sales')->group(function () {
        Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::post('sales/{sale}/refund', [SaleController::class, 'refund'])->name('sales.refund');
    });

    Route::middleware('can:manage-withdrawals')->group(function () {
        Route::get('withdrawals', [WithdrawalController::class, 'index'])->name('withdrawals.index');
        Route::post('withdrawals/{withdrawal}/approve', [WithdrawalController::class, 'approve'])->name('withdrawals.approve');
        Route::post('withdrawals/{withdrawal}/processing', [WithdrawalController::class, 'startProcessing'])->name('withdrawals.processing');
        Route::post('withdrawals/{withdrawal}/paid', [WithdrawalController::class, 'markPaid'])->name('withdrawals.paid');
        Route::post('withdrawals/{withdrawal}/reject', [WithdrawalController::class, 'reject'])->name('withdrawals.reject');
    });

    Route::middleware('can:manage-kyc')->group(function () {
        Route::get('kyc', SectionPlaceholderController::class)->defaults('section', 'KYC Review')->defaults('phase', 12)->name('kyc.index');
    });

    Route::middleware('can:view-reports')->group(function () {
        Route::get('financial', [FinancialController::class, 'index'])->name('financial.index');
        Route::post('financial/expenses', [FinancialController::class, 'storeExpense'])->name('financial.expenses.store');
        Route::delete('financial/expenses/{expense}', [FinancialController::class, 'destroyExpense'])->name('financial.expenses.destroy');
        Route::post('financial/income', [FinancialController::class, 'storeIncome'])->name('financial.income.store');
        Route::delete('financial/income/{income}', [FinancialController::class, 'destroyIncome'])->name('financial.income.destroy');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('reports/ranks', [ReportController::class, 'ranks'])->name('reports.ranks');
    });

    Route::middleware('can:manage-settings')->group(function () {
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        // Discretionary payouts are super-admin only.
        Route::post('members/{member}/performance-bonus', [BonusController::class, 'performance'])->name('members.performance-bonus');
    });
});
