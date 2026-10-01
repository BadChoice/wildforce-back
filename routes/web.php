<?php

use App\Http\Controllers\Auth\AppleLoginController;
use App\Http\Controllers\Auth\GoogleLoginController;
use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\Billing\StripeCheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExerciseCatalogController;
use App\Http\Controllers\WorkoutPlansController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::post('auth/apple', AppleLoginController::class)->middleware('throttle:5,1')->name('apple.login');
Route::post('auth/google', GoogleLoginController::class)->middleware('throttle:5,1')->name('google.login');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('billing', BillingController::class)->name('billing.index');
    Route::view('clients', 'clients.index')->name('clients.index');
    Route::get('exercises', [ExerciseCatalogController::class, 'index'])->name('exercises.index');
    Route::get('workout-plans', [WorkoutPlansController::class, 'index'])->name('workout-plans.index');
    Route::get('workout-plans/{workoutPlan}', [WorkoutPlansController::class, 'show'])->name('workout-plans.show');
    Route::view('workout-templates', 'workout-templates.index')->name('workout-templates.index');
    Route::post('billing/checkout/{plan}/{interval}', StripeCheckoutController::class)
        ->whereIn('interval', ['monthly', 'yearly'])
        ->name('billing.checkout');
});

require __DIR__.'/settings.php';
