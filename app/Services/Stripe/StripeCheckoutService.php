<?php

namespace App\Services\Stripe;

use App\Enums\SubscriptionPlan;
use App\Models\User;
use LogicException;
use Stripe\StripeClient;

class StripeCheckoutService
{
    public function __construct(private StripePriceCatalog $stripePrices) {}

    public function create(User $user, SubscriptionPlan $plan, string $interval): string
    {
        $priceId = $this->stripePrices->priceIdFor($plan, $interval);
        $checkoutSession = $this->stripe()->checkout->sessions->create([
            'client_reference_id' => $user->id,
            'customer_email' => $user->email,
            'line_items' => [[
                'price' => $priceId,
                'quantity' => 1,
            ]],
            'mode' => 'subscription',
            'success_url' => route('dashboard'),
            'cancel_url' => route('dashboard'),
            'subscription_data' => [
                'metadata' => ['user_id' => $user->id],
            ],
        ]);

        if (! is_string($checkoutSession->url) || $checkoutSession->url === '') {
            throw new LogicException('Stripe did not return a Checkout URL.');
        }

        return $checkoutSession->url;
    }

    private function stripe(): StripeClient
    {
        $secret = config('services.stripe.secret');
        if (! is_string($secret) || $secret === '') {
            throw new LogicException('Stripe is not configured.');
        }

        return new StripeClient($secret);
    }
}
