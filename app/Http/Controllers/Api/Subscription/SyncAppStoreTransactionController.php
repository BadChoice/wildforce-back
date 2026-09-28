<?php

namespace App\Http\Controllers\Api\Subscription;

use App\Http\Controllers\Controller;
use App\Services\AppStore\AppStoreSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyncAppStoreTransactionController extends Controller
{
    public function __invoke(
        Request $request,
        AppStoreSubscriptionService $subscriptionService,
    ): JsonResponse {
        $validated = $request->validate([
            'signed_transaction' => ['required', 'string', 'max:50000'],
        ]);

        $subscription = $subscriptionService->synchronize(
            $request->user(),
            $validated['signed_transaction'],
        );

        return response()->json(['data' => $subscription]);
    }
}
