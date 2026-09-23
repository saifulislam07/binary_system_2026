<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

/*
| Admin panel routes. Loaded from bootstrap/app.php with the `web`
| middleware group, the `admin` URL prefix, the `admin.` name prefix and
| the UseAdminGuard middleware already applied.
*/

Route::middleware('guest:admin')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');
});

Route::middleware('auth:admin')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    Route::redirect('/', '/admin/dashboard');
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});
