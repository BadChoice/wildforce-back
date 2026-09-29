<?php

namespace App\Http\Controllers\Settings;

use App\Enums\SubscriptionProvider;
use App\Http\Controllers\Controller;
use App\Services\Stripe\StripeCustomerPortalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StripeCustomerPortalController extends Controller
{
    public function __invoke(Request $request, StripeCustomerPortalService $stripeCustomerPortal): RedirectResponse
    {
        $subscription = $request->user()->subscription()->first();

        abort_unless($subscription?->provider === SubscriptionProvider::Stripe, 404);

        return redirect()->away($stripeCustomerPortal->create($subscription));
    }
}
