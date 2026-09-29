<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Stripe\StripeCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StripeCheckoutController extends Controller
{
    public function __invoke(Request $request, StripeCheckoutService $stripeCheckout): RedirectResponse
    {
        $checkoutUrl = $stripeCheckout->create($request->user(), (string) $request->route('interval'));

        return redirect()->away($checkoutUrl);
    }
}
