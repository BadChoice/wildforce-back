<?php

namespace App\Services\Stripe;

use App\Models\User;
use LogicException;
use Stripe\StripeClient;

class StripeCheckoutService
{
    public function create(User $user, string $interval): string
    {
        $priceId = $this->priceIdFor($interval);
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

    private function priceIdFor(string $interval): string
    {
        $priceId = config("services.stripe.friend_prices.{$interval}.price_id");
        if (! is_string($priceId) || $priceId === '') {
            throw new LogicException("The Stripe {$interval} price is not configured.");
        }

        return $priceId;
    }
}
