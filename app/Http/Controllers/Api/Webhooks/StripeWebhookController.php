<?php

namespace App\Http\Controllers\Api\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\Stripe\StripeSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeSubscriptionService $stripeSubscriptions): Response
    {
        try {
            $stripeSubscriptions->synchronizeWebhook(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            abort(400, 'Invalid Stripe webhook.');
        }

        return response()->noContent();
    }
}
