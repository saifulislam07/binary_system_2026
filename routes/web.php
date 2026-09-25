<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IncomeController;
use App\Http\Controllers\KycController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentCallbackController;
use App\Http\Controllers\PaymentSimulatorController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\SponsorLookupController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WithdrawalController;
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

    Route::get('withdrawals', [WithdrawalController::class, 'index'])->name('withdrawals.index');
    Route::post('withdrawals', [WithdrawalController::class, 'store'])->middleware('throttle:5,1')->name('withdrawals.store');

    Route::get('kyc', [KycController::class, 'index'])->name('kyc.index');
    Route::post('kyc', [KycController::class, 'store'])->middleware('throttle:5,1')->name('kyc.store');
    Route::patch('kyc/address', [KycController::class, 'updateAddress'])->name('kyc.address');

    Route::middleware('member.active')->group(function () {
        Route::get('income', [IncomeController::class, 'index'])->name('income.index');
        Route::get('team', [TeamController::class, 'index'])->name('team.index');
        Route::get('team/tree/{member:member_code}', [TeamController::class, 'tree'])->middleware('throttle:60,1')->name('team.tree');
        Route::get('referral', [ReferralController::class, 'index'])->name('referral.index');
    });
});

// Gateway redirects + IPNs: no auth, CSRF-exempt (see bootstrap/app.php); verified server-to-server.
Route::match(['get', 'post'], 'payments/{gateway}/callback', PaymentCallbackController::class)
    ->middleware('throttle:60,1')
    ->name('payments.callback');
Route::get('payments/simulator/{ref}', PaymentSimulatorController::class)->name('payments.simulator.show');

require __DIR__.'/settings.php';
