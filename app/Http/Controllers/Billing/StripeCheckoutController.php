<?php

namespace App\Http\Controllers\Billing;

use App\Enums\SubscriptionPlan;
use App\Http\Controllers\Controller;
use App\Services\Stripe\StripeCheckoutService;
use App\Services\Stripe\StripePriceCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StripeCheckoutController extends Controller
{
    public function __invoke(
        Request $request,
        StripeCheckoutService $stripeCheckout,
        StripePriceCatalog $stripePrices,
    ): RedirectResponse {
        $user = $request->user();
        $subscription = $user->subscription()->first();

        if ($subscription !== null && ! $subscription->plan->isTemporaryAccess()) {
            return to_route($this->billingRoute((string) $request->route('plan')));
        }

        $plan = SubscriptionPlan::tryFrom((string) $request->route('plan'));
        $interval = (string) $request->route('interval');
        abort_unless($plan !== null && $stripePrices->hasPrice($plan, $interval), 404);

        $checkoutUrl = $stripeCheckout->create($user, $plan, $interval);

        return redirect()->away($checkoutUrl);
    }

    private function billingRoute(string $planValue): string
    {
        $plan = SubscriptionPlan::tryFrom($planValue);

        if ($plan === SubscriptionPlan::Friends) {
            return 'subscription.billing.friends';
        }

        if ($plan?->isCoachPlan()) {
            return 'subscription.billing.coach';
        }

        return 'subscription.billing';
    }
}
