<?php

namespace App\Services\Stripe;

use App\Enums\SubscriptionPlan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Number;
use LogicException;
use Stripe\Exception\ApiErrorException;
use Stripe\Price;
use Stripe\StripeClient;

class StripePriceDisplayService
{
    public function __construct(private StripePriceCatalog $stripePrices) {}

    public function formattedPriceFor(SubscriptionPlan $plan, string $interval): ?string
    {
        $secret = config('services.stripe.secret');
        if (! is_string($secret) || $secret === '') {
            return null;
        }

        $priceId = $this->stripePrices->priceIdFor($plan, $interval);

        try {
            return Cache::remember(
                "stripe-price-display.{$priceId}",
                now()->addHour(),
                fn (): ?string => $this->formattedPrice($this->stripe()->prices->retrieve($priceId)),
            );
        } catch (ApiErrorException $exception) {
            report($exception);

            return null;
        }
    }

    private function formattedPrice(Price $price): ?string
    {
        if ($price->unit_amount === null || $price->currency === '') {
            return null;
        }

        $formattedPrice = Number::currency($price->unit_amount / 100, mb_strtoupper($price->currency));

        return is_string($formattedPrice) ? $formattedPrice : null;
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
