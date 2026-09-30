<?php

namespace App\Http\Controllers\Api\Subscription;

use App\Http\Controllers\Controller;
use App\Services\GooglePlay\GooglePlaySubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncGooglePlayPurchaseController extends Controller
{
    public function __invoke(Request $request, GooglePlaySubscriptionService $subscriptionService): JsonResponse
    {
        $validated = $request->validate([
            'purchase_token' => ['required', 'string', 'max:4096'],
            'obfuscated_account_id' => ['required', 'string', 'size:64'],
        ]);

        $subscription = $subscriptionService->synchronize(
            $request->user(),
            $validated['purchase_token'],
            $validated['obfuscated_account_id'],
        );

        return response()->json(['data' => $subscription]);
    }
}
