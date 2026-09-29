<?php

namespace App\Services\Stripe;

use App\Enums\SubscriptionPlan;
use LogicException;

class StripePriceCatalog
{
    /**
     * @return list<array{plan: SubscriptionPlan, intervals: list<string>}>
     */
    public function availablePlans(): array
    {
        $plans = [];

        foreach ($this->prices() as $planValue => $intervals) {
            $plan = SubscriptionPlan::tryFrom($planValue);
            if ($plan === null || ! is_array($intervals)) {
                continue;
            }

            $availableIntervals = [];
            foreach ($intervals as $interval => $price) {
                if (is_string($interval) && $this->configuredPriceId($price) !== null) {
                    $availableIntervals[] = $interval;
                }
            }

            if ($availableIntervals !== []) {
                $plans[] = ['plan' => $plan, 'intervals' => $availableIntervals];
            }
        }

        return $plans;
    }

    public function hasPrice(SubscriptionPlan $plan, string $interval): bool
    {
        return $this->configuredPriceId(data_get($this->prices(), "{$plan->value}.{$interval}")) !== null;
    }

    public function priceIdFor(SubscriptionPlan $plan, string $interval): string
    {
        $priceId = $this->configuredPriceId(data_get($this->prices(), "{$plan->value}.{$interval}"));
        if ($priceId === null) {
            throw new LogicException("The Stripe {$plan->value} {$interval} price is not configured.");
        }

        return $priceId;
    }

    public function planForPriceId(string $priceId): ?SubscriptionPlan
    {
        foreach ($this->prices() as $planValue => $intervals) {
            $plan = SubscriptionPlan::tryFrom($planValue);
            if ($plan === null || ! is_array($intervals)) {
                continue;
            }

            foreach ($intervals as $price) {
                if ($this->configuredPriceId($price) === $priceId) {
                    return $plan;
                }
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function prices(): array
    {
        $prices = config('services.stripe.prices', []);

        return is_array($prices) ? $prices : [];
    }

    private function configuredPriceId(mixed $price): ?string
    {
        $priceId = is_array($price) ? $price['price_id'] ?? null : null;

        return is_string($priceId) && $priceId !== '' ? $priceId : null;
    }
}
