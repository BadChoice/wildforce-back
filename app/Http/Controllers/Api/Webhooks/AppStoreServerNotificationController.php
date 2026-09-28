<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\AppStore\AppStoreSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class AppStoreServerNotificationController extends Controller
{
    public function __invoke(Request $request, AppStoreSubscriptionService $subscriptionService): Response
    {
        $signedPayload = $request->string('signedPayload')->toString();
        if ($signedPayload === '') {
            abort(400, 'Missing signedPayload.');
        }

        try {
            $subscriptionService->synchronizeNotification($signedPayload);
        } catch (ValidationException) {
            abort(400, 'Invalid App Store notification.');
        }

        return response()->noContent();
    }
}
