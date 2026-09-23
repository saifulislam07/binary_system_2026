<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SponsorLookupController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::get('sponsors/{code}', SponsorLookupController::class)
    ->middleware('throttle:30,1')
    ->where('code', '[A-Za-z]{3}-\d{6,}')
    ->name('sponsors.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

require __DIR__.'/settings.php';
