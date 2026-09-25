<?php

use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Sync\NutritionPlanSyncController;
use App\Http\Controllers\Api\Sync\SyncPullController;
use App\Http\Controllers\Api\Sync\SyncPushController;
use App\Http\Controllers\Api\Sync\WorkoutDaySyncController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', RegisterController::class)->middleware('throttle:5,1');
Route::post('/auth/login', LoginController::class)->middleware('throttle:5,1');

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('/sync/push', SyncPushController::class);
    Route::get('/sync/pull', SyncPullController::class);
    Route::apiResource('/sync/workout-days', WorkoutDaySyncController::class)->only(['index', 'store']);
    Route::apiResource('/sync/nutrition-plans', NutritionPlanSyncController::class)->only(['index', 'store']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
