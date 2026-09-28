<?php

use App\Http\Controllers\ExerciseCatalogController;
use App\Http\Controllers\WorkoutPlansController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::get('exercises', [ExerciseCatalogController::class, 'index'])->name('exercises.index');
    Route::get('workout-plans', [WorkoutPlansController::class, 'index'])->name('workout-plans.index');
});

require __DIR__.'/settings.php';
