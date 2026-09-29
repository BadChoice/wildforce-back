<?php

namespace App\Http\Controllers\Billing;

use App\Enums\SubscriptionPlan;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function __invoke(Request $request): View
    {
        $subscription = $request->user()->subscription()->first();

        return view('billing.index', [
            'canCheckout' => $subscription === null || $subscription->plan === SubscriptionPlan::Trial,
            'subscription' => $subscription,
        ]);
    }
}
