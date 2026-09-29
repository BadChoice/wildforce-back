<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Stripe\StripePriceCatalog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function __invoke(Request $request, StripePriceCatalog $stripePrices): View
    {
        $subscription = $request->user()->subscription()->first();

        return view('billing.index', [
            'canCheckout' => $subscription === null || $subscription->plan->isTemporaryAccess(),
            'checkoutPlans' => $stripePrices->availablePlans(),
            'subscription' => $subscription,
        ]);
    }
}
