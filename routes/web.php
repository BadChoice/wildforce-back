<?php

use App\Http\Controllers\ExerciseCatalogController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::get('exercises', [ExerciseCatalogController::class, 'index'])->name('exercises.index');
});

require __DIR__.'/settings.php';
