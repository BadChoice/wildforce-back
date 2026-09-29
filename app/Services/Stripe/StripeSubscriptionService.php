<?php

namespace App\Services\Stripe;

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

    private function webhookSecret(): string
    {
        $webhookSecret = config('services.stripe.webhook_secret');
        if (! is_string($webhookSecret) || $webhookSecret === '') {
            throw new \LogicException('Stripe webhooks are not configured.');
        }

        return $webhookSecret;
    }
}
