<?php

namespace App\Services\Stripe;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionProvider;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stripe\Subscription as StripeSubscription;
use Stripe\Webhook;

class StripeSubscriptionService
{
    /**
     * Synchronize a Stripe subscription event with the user's app access.
     */
    public function synchronizeWebhook(string $payload, string $signature): ?Subscription
    {
        $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret());

        if (! in_array($event->type, [
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted',
        ], true) || ! $event->data->object instanceof StripeSubscription) {
            return null;
        }

        /** @var array<string, mixed> $stripeSubscription */
        $stripeSubscription = $event->data->object->toArray();

        return DB::transaction(fn (): ?Subscription => $this->persist($stripeSubscription));
    }

    /** @param array<string, mixed> $stripeSubscription */
    private function persist(array $stripeSubscription): ?Subscription
    {
        $subscriptionId = $stripeSubscription['id'] ?? null;
        if (! is_string($subscriptionId) || $subscriptionId === '') {
            return null;
        }

        $existingSubscription = Subscription::query()
            ->where('provider', SubscriptionProvider::Stripe)
            ->where('provider_reference', $subscriptionId)
            ->lockForUpdate()
            ->first();

        $user = $existingSubscription !== null
            ? User::query()->find($existingSubscription->user_id)
            : $this->userFor($stripeSubscription);
        $plan = $this->planFor($stripeSubscription);
        if ($user === null || $plan === null) {
            return null;
        }

        $status = $this->statusFor($stripeSubscription['status'] ?? null);
        $autoRenews = ($stripeSubscription['cancel_at_period_end'] ?? false) !== true
            && $status !== SubscriptionStatus::Expired;
        $attributes = [
            'plan' => $plan,
            'provider' => SubscriptionProvider::Stripe,
            'status' => $status,
            'auto_renews' => $autoRenews,
            ...$this->billingDetailsFor($stripeSubscription),
            'provider_reference' => $subscriptionId,
            'starts_at' => $this->timestamp($stripeSubscription['start_date'] ?? null),
            'renews_at' => $this->timestamp($stripeSubscription['current_period_end'] ?? null),
            'cancelled_at' => $autoRenews ? null : $this->timestamp($stripeSubscription['canceled_at'] ?? null) ?? now(),
        ];

        if ($existingSubscription !== null) {
            $existingSubscription->fill($attributes)->save();

            return $existingSubscription;
        }

        $user->subscription()->lockForUpdate()->first()?->delete();

        $subscription = new Subscription($attributes);
        if ($user->subscription()->save($subscription) === false) {
            throw new \LogicException('Could not store the Stripe subscription.');
        }

        return $subscription;
    }

    /** @param array<string, mixed> $stripeSubscription */
    private function userFor(array $stripeSubscription): ?User
    {
        $userId = data_get($stripeSubscription, 'metadata.user_id');

        return is_string($userId) && Str::isUuid($userId)
            ? User::query()->find($userId)
            : null;
    }

    /** @param array<string, mixed> $stripeSubscription */
    private function planFor(array $stripeSubscription): ?SubscriptionPlan
    {
        $priceId = data_get($stripeSubscription, 'items.data.0.price.id');
        if (! is_string($priceId)) {
            return null;
        }

        $prices = config('services.stripe.friend_prices', []);
        if (! is_array($prices)) {
            return null;
        }

        foreach ($prices as $price) {
            if (! is_array($price) || ($price['price_id'] ?? null) !== $priceId) {
                continue;
            }

            return is_string($price['plan'] ?? null)
                ? SubscriptionPlan::tryFrom($price['plan'])
                : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $stripeSubscription
     * @return array{billing_amount: string|null, billing_currency: string|null, billing_interval: BillingInterval|null, billing_interval_count: int|null}
     */
    private function billingDetailsFor(array $stripeSubscription): array
    {
        $amount = data_get($stripeSubscription, 'items.data.0.price.unit_amount_decimal');
        $currency = data_get($stripeSubscription, 'items.data.0.price.currency');
        $interval = data_get($stripeSubscription, 'items.data.0.price.recurring.interval');
        $intervalCount = data_get($stripeSubscription, 'items.data.0.price.recurring.interval_count');

        return [
            'billing_amount' => $this->amountFromCents($amount),
            'billing_currency' => $this->currency($currency),
            'billing_interval' => is_string($interval) ? BillingInterval::tryFrom($interval) : null,
            'billing_interval_count' => is_numeric($intervalCount) ? (int) $intervalCount : null,
        ];
    }

    private function statusFor(mixed $status): SubscriptionStatus
    {
        return match ($status) {
            'active', 'trialing' => SubscriptionStatus::Active,
            'past_due' => SubscriptionStatus::GracePeriod,
            'canceled', 'incomplete_expired', 'unpaid' => SubscriptionStatus::Expired,
            default => SubscriptionStatus::Pending,
        };
    }

    private function timestamp(mixed $timestamp): ?Carbon
    {
        return is_numeric($timestamp) ? Carbon::createFromTimestampUTC((int) $timestamp) : null;
    }

    private function amountFromCents(mixed $amount): ?string
    {
        return is_numeric($amount) && (float) $amount >= 0
            ? number_format((float) $amount / 100, 3, '.', '')
            : null;
    }

    private function currency(mixed $currency): ?string
    {
        return is_string($currency) && mb_strlen($currency) === 3
            ? mb_strtoupper($currency)
            : null;
    }

    private function webhookSecret(): string
    {
        $webhookSecret = config('services.stripe.webhook_secret');
        if (! is_string($webhookSecret) || $webhookSecret === '') {
            throw new \LogicException('Stripe webhooks are not configured.');
        }

        return $webhookSecret;
    }
}
