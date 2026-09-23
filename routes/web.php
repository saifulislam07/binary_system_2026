<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentCallbackController;
use App\Http\Controllers\PaymentSimulatorController;
use App\Http\Controllers\SponsorLookupController;
use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('sponsors/{code}', SponsorLookupController::class)
    ->middleware('throttle:30,1')
    ->where('code', '[A-Za-z]{3}-\d{6,}')
    ->name('sponsors.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
    Route::get('orders/{order:order_number}', [OrderController::class, 'show'])->name('orders.show');

    Route::get('wallet', [WalletController::class, 'index'])->name('wallet.index');
});

// Gateway redirects + IPNs: no auth, CSRF-exempt (see bootstrap/app.php); verified server-to-server.
Route::match(['get', 'post'], 'payments/{gateway}/callback', PaymentCallbackController::class)
    ->middleware('throttle:60,1')
    ->name('payments.callback');
Route::get('payments/simulator/{ref}', PaymentSimulatorController::class)->name('payments.simulator.show');

require __DIR__.'/settings.php';
