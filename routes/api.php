<?php

use App\Http\Controllers\Api\Access\RedeemDemoCodeController;
use App\Http\Controllers\Api\Account\AppleIdentityController;
use App\Http\Controllers\Api\Account\AvatarController;
use App\Http\Controllers\Api\Account\CoachController;
use App\Http\Controllers\Api\Account\GoogleIdentityController;
use App\Http\Controllers\Api\Ai\CompletionController;
use App\Http\Controllers\Api\Auth\AppleAuthenticationController;
use App\Http\Controllers\Api\Auth\GoogleAuthenticationController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\BodyProgressPhotoController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\NutritionLogItemImageController;
use App\Http\Controllers\Api\Nutrition\NutritionPlanGenerationController;
use App\Http\Controllers\Api\Subscription\SubscriptionAccessController;
use App\Http\Controllers\Api\Subscription\SyncAppStoreTransactionController;
use App\Http\Controllers\Api\Subscription\SyncGooglePlayPurchaseController;
use App\Http\Controllers\Api\Sync\BatchSyncPullController;
use App\Http\Controllers\Api\Sync\NutritionPlanSyncController;
use App\Http\Controllers\Api\Sync\SyncPullController;
use App\Http\Controllers\Api\Sync\SyncPushController;
use App\Http\Controllers\Api\Sync\WorkoutDaySyncController;
use App\Http\Controllers\Api\Webhooks\AppStoreServerNotificationController;
use App\Http\Controllers\Api\Webhooks\StripeWebhookController;
use App\Http\Controllers\Api\Workouts\WorkoutPlanGenerationController;
use App\Http\Middleware\EnsureUserHasAppAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', RegisterController::class)->middleware('throttle:5,1');
Route::post('/auth/login', LoginController::class)->middleware('throttle:5,1');
Route::post('/auth/apple', AppleAuthenticationController::class)->middleware('throttle:5,1');
Route::post('/auth/google', GoogleAuthenticationController::class)->middleware('throttle:5,1');
Route::post('/webhooks/app-store', AppStoreServerNotificationController::class)->middleware('throttle:120,1');
Route::post('/webhooks/stripe', StripeWebhookController::class)->middleware('throttle:120,1');

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('/account/identities', [AppleIdentityController::class, 'index']);
    Route::post('/account/identities/apple', [AppleIdentityController::class, 'store']);
    Route::delete('/account/identities/apple', [AppleIdentityController::class, 'destroy']);
    Route::post('/account/identities/google', [GoogleIdentityController::class, 'store']);
    Route::delete('/account/identities/google', [GoogleIdentityController::class, 'destroy']);
    Route::post('/users/avatar', AvatarController::class);
    Route::post('/subscription/redeem-code', RedeemDemoCodeController::class);
    Route::post('/subscription/app-store/transactions', SyncAppStoreTransactionController::class);
    Route::post('/subscription/google-play/purchases', SyncGooglePlayPurchaseController::class);
    Route::get('/subscription/access', SubscriptionAccessController::class);
    Route::post('/feedback', FeedbackController::class);

    Route::middleware(EnsureUserHasAppAccess::class)->group(function () {
        Route::post('/ai/completions', CompletionController::class)->middleware('throttle:10,1');
        Route::post('/workout-plans/generate', WorkoutPlanGenerationController::class)->middleware(['idempotency', 'throttle:5,1']);
        Route::post('/nutrition-plans/generate', NutritionPlanGenerationController::class)->middleware(['idempotency', 'throttle:5,1']);
        Route::get('/account/coaches', CoachController::class);
        Route::post('/body-progress-photo-sessions/{bodyProgressPhotoSession}/photos/{angle}', [BodyProgressPhotoController::class, 'store'])
            ->whereIn('angle', ['profile', 'front', 'torso']);
        Route::post('/nutrition-log-items/{nutritionLogItem}/image', [NutritionLogItemImageController::class, 'store']);
        Route::post('/sync/push', SyncPushController::class);
        Route::get('/sync/pull', SyncPullController::class);
        Route::post('/sync/pull/batch', BatchSyncPullController::class);
        Route::apiResource('/sync/workout-days', WorkoutDaySyncController::class)->only(['index', 'store']);
        Route::apiResource('/sync/nutrition-plans', NutritionPlanSyncController::class)->only(['index', 'store']);
    });
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
