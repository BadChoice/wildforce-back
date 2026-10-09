<?php

namespace App\Http\Controllers\Billing;

use App\Enums\SubscriptionPlan;
use App\Http\Controllers\Controller;
use App\Services\Stripe\StripePriceCatalog;
use App\Services\Stripe\StripePriceDisplayService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function premium(
        Request $request,
        StripePriceCatalog $stripePrices,
        StripePriceDisplayService $stripePriceDisplay,
    ): View {
        return $this->showPlans(
            $request,
            $stripePrices,
            $stripePriceDisplay,
            [SubscriptionPlan::Premium],
        );
    }

    public function friends(
        Request $request,
        StripePriceCatalog $stripePrices,
        StripePriceDisplayService $stripePriceDisplay,
    ): View {
        return $this->showPlans(
            $request,
            $stripePrices,
            $stripePriceDisplay,
            [SubscriptionPlan::Friends],
        );
    }

    public function coach(
        Request $request,
        StripePriceCatalog $stripePrices,
        StripePriceDisplayService $stripePriceDisplay,
    ): View {
        return $this->showPlans(
            $request,
            $stripePrices,
            $stripePriceDisplay,
            [SubscriptionPlan::CoachBasic, SubscriptionPlan::CoachStudio, SubscriptionPlan::CoachPro],
        );
    }

    /**
     * @param  list<SubscriptionPlan>  $plans
     */
    private function showPlans(
        Request $request,
        StripePriceCatalog $stripePrices,
        StripePriceDisplayService $stripePriceDisplay,
        array $plans,
    ): View {
        $subscription = $request->user()->subscription()->first();

        return view('billing.index', [
            'canCheckout' => $subscription === null || $subscription->plan->isTemporaryAccess(),
            'checkoutPlans' => collect($stripePrices->availablePlans())
                ->filter(fn (array $checkoutPlan): bool => in_array($checkoutPlan['plan'], $plans, true))
                ->map(function (array $checkoutPlan) use ($stripePriceDisplay): array {
                    return [
                        'plan' => $checkoutPlan['plan'],
                        'intervals' => collect($checkoutPlan['intervals'])
                            ->map(fn (string $interval): array => [
                                'value' => $interval,
                                'price' => $stripePriceDisplay->formattedPriceFor($checkoutPlan['plan'], $interval),
                            ])
                            ->all(),
                    ];
                })
                ->all(),
            'subscription' => $subscription,
        ]);
    }
}
