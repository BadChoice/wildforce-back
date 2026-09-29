<?php

namespace App\Http\Controllers\Billing;

use App\Enums\SubscriptionPlan;
use App\Http\Controllers\Controller;
use App\Services\Stripe\StripeCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StripeCheckoutController extends Controller
{
    public function __invoke(Request $request, StripeCheckoutService $stripeCheckout): RedirectResponse
    {
        $user = $request->user();
        $subscription = $user->subscription()->first();

        if ($subscription !== null && $subscription->plan !== SubscriptionPlan::Trial) {
            return to_route('billing.index');
        }

        $checkoutUrl = $stripeCheckout->create($user, (string) $request->route('interval'));

        return redirect()->away($checkoutUrl);
    }
}
