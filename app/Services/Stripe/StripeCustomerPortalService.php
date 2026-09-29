<?php

namespace App\Services\Stripe;

use App\Models\Subscription;
use LogicException;
use Stripe\StripeClient;

class StripeCustomerPortalService
{
    public function create(Subscription $subscription): string
    {
        $subscriptionReference = $subscription->provider_reference;
        if (! is_string($subscriptionReference) || $subscriptionReference === '') {
            throw new LogicException('The Stripe subscription reference is missing.');
        }

        $stripeSubscription = $this->stripe()->subscriptions->retrieve($subscriptionReference);
        if (! is_string($stripeSubscription->customer) || $stripeSubscription->customer === '') {
            throw new LogicException('The Stripe customer reference is missing.');
        }

        $portalSession = $this->stripe()->billingPortal->sessions->create([
            'customer' => $stripeSubscription->customer,
            'return_url' => route('subscription.edit'),
        ]);

        if ($portalSession->url === '') {
            throw new LogicException('Stripe did not return a Customer Portal URL.');
        }

        return $portalSession->url;
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
