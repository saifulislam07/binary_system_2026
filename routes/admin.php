<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\BonusController;
use App\Http\Controllers\Admin\BrandController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\FinancialController;
use App\Http\Controllers\Admin\FraudFlagController;
use App\Http\Controllers\Admin\HealthController;
use App\Http\Controllers\Admin\KycController;
use App\Http\Controllers\Admin\MemberController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\PendingMemberController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\RankController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TreeController;
use App\Http\Controllers\Admin\WithdrawalController;
use App\Http\Middleware\EnsureAdminIsActive;
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

Route::middleware(['auth:admin', EnsureAdminIsActive::class])->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // Every admin can change their own password.
    Route::get('account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('account/password', [AccountController::class, 'updatePassword'])->middleware('throttle:6,1')->name('account.password');

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

        Route::get('fraud', [FraudFlagController::class, 'index'])->name('fraud.index');
        Route::post('fraud/{flag}/review', [FraudFlagController::class, 'review'])->name('fraud.review');
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
        Route::get('kyc', [KycController::class, 'index'])->name('kyc.index');
        Route::get('kyc/{document}', [KycController::class, 'show'])->name('kyc.show');
        Route::get('kyc/{document}/media/{media}', [KycController::class, 'media'])->name('kyc.media');
        Route::post('kyc/{document}/approve', [KycController::class, 'approve'])->name('kyc.approve');
        Route::post('kyc/{document}/reject', [KycController::class, 'reject'])->name('kyc.reject');
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

    Route::middleware('can:send-announcements')->group(function () {
        Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('announcements/preview', [AnnouncementController::class, 'preview'])->name('announcements.preview');
        Route::post('announcements', [AnnouncementController::class, 'store'])->middleware('throttle:10,1')->name('announcements.store');
    });

    Route::middleware('can:manage-settings')->group(function () {
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('health', HealthController::class)->name('health');

        // Catalog and rank rules (rules #4 and #11).
        Route::get('packages', [PackageController::class, 'index'])->name('packages.index');
        Route::get('packages/create', [PackageController::class, 'create'])->name('packages.create');
        Route::post('packages', [PackageController::class, 'store'])->name('packages.store');
        Route::get('packages/{package}/edit', [PackageController::class, 'edit'])->name('packages.edit');
        Route::put('packages/{package}', [PackageController::class, 'update'])->name('packages.update');
        Route::get('ranks', [RankController::class, 'index'])->name('ranks.index');
        Route::put('ranks', [RankController::class, 'updateRanks'])->name('ranks.update');
        Route::post('bonus-rules', [RankController::class, 'storeBonusRule'])->name('bonus-rules.store');
        Route::put('bonus-rules/{rule}', [RankController::class, 'updateBonusRule'])->name('bonus-rules.update');
        // Discretionary payouts are super-admin only.
        Route::post('members/{member}/performance-bonus', [BonusController::class, 'performance'])->name('members.performance-bonus');
    });

    // Shop catalog: categories, brands and products shown on the public shop.
    Route::middleware('can:manage-catalog')->group(function () {
        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('categories/create', [CategoryController::class, 'create'])->name('categories.create');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::get('categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::get('brands', [BrandController::class, 'index'])->name('brands.index');
        Route::get('brands/create', [BrandController::class, 'create'])->name('brands.create');
        Route::post('brands', [BrandController::class, 'store'])->name('brands.store');
        Route::get('brands/{brand}/edit', [BrandController::class, 'edit'])->name('brands.edit');
        Route::put('brands/{brand}', [BrandController::class, 'update'])->name('brands.update');
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/create', [ProductController::class, 'create'])->name('products.create');
        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::get('products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('products/{product}', [ProductController::class, 'update'])->name('products.update');
    });

    Route::middleware('can:manage-admins')->group(function () {
        Route::get('admins', [AdminUserController::class, 'index'])->name('admins.index');
        Route::get('admins/create', [AdminUserController::class, 'create'])->name('admins.create');
        Route::post('admins', [AdminUserController::class, 'store'])->name('admins.store');
        Route::get('admins/{admin}/edit', [AdminUserController::class, 'edit'])->name('admins.edit');
        Route::put('admins/{admin}', [AdminUserController::class, 'update'])->name('admins.update');
        Route::post('roles', [AdminUserController::class, 'storeRole'])->name('roles.store');
        Route::put('roles/{role}', [AdminUserController::class, 'updateRole'])->name('roles.update');
    });
});
