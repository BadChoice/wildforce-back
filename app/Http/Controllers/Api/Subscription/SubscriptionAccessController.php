<?php

namespace App\Http\Controllers\Api\Subscription;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionAccessController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $subscription = $request->user()->subscription;

        return response()->json([
            'data' => [
                'has_access' => $request->user()->hasAppAccess(),
                'status' => $subscription?->status?->value,
                'plan' => $subscription?->plan?->value,
                'renews_at' => $subscription?->renews_at?->toISOString(),
            ],
        ]);
    }
}
