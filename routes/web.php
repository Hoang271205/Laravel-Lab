<?php

use App\Exceptions\OrderCannotProcessException;
use App\Http\Controllers\ApiTokenManagerController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomepageController;
use App\Http\Controllers\OrderCompleteController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomepageController::class, 'index'])->name('homepage');
Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
Route::get('/checkout/{order}', [CheckoutController::class, 'show'])->name('checkout.show');
Route::get('/order-complete/{order}', OrderCompleteController::class)->name('orders.complete');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/api-tokens', [ApiTokenManagerController::class, 'index'])->name('apiTokens.index');
});

if (app()->environment('local', 'testing')) {
    Route::view('/section3', 'section3');
    Route::get('/section3/server-error', fn () => throw new RuntimeException('password=never-expose-this-secret'));
    Route::get('/section3/business-error', fn () => throw new OrderCannotProcessException(42));
}
