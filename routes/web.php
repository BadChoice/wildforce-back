<?php

use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\Billing\StripeCheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExerciseCatalogController;
use App\Http\Controllers\WorkoutPlansController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('billing', BillingController::class)->name('billing.index');
    Route::view('clients', 'clients.index')->name('clients.index');
    Route::get('exercises', [ExerciseCatalogController::class, 'index'])->name('exercises.index');
    Route::get('workout-plans', [WorkoutPlansController::class, 'index'])->name('workout-plans.index');
    Route::post('billing/checkout/{interval}', StripeCheckoutController::class)
        ->whereIn('interval', ['monthly', 'yearly'])
        ->name('billing.checkout');
});

require __DIR__.'/settings.php';
