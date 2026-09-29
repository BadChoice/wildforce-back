<?php

use App\Enums\BillingInterval;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionProvider;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use App\Services\Stripe\StripeSubscriptionService;

test('it stores a verified monthly Stripe subscription for the referenced user', function () {
    config()->set('services.stripe.webhook_secret', 'whsec_test');
    config()->set('services.stripe.friend_prices.monthly.price_id', 'price_friend_monthly');

    $user = User::factory()->create();
    $payload = stripeSubscriptionPayload($user, 'customer.subscription.created');

    $subscription = app(StripeSubscriptionService::class)->synchronizeWebhook(
        $payload,
        stripeSignature($payload),
    );

    expect($subscription)
        ->plan->toBe(SubscriptionPlan::MemberMonthly)
        ->provider->toBe(SubscriptionProvider::Stripe)
        ->status->toBe(SubscriptionStatus::Active)
        ->auto_renews->toBeTrue()
        ->billing_amount->toBe('4.200')
        ->billing_currency->toBe('EUR')
        ->billing_interval->toBe(BillingInterval::Month)
        ->billing_interval_count->toBe(1)
        ->provider_reference->toBe('sub_friend');
    expect($user->fresh()->subscription?->id)->toBe($subscription?->id);
});

test('it updates a Stripe subscription when Stripe disables renewal', function () {
    config()->set('services.stripe.webhook_secret', 'whsec_test');
    config()->set('services.stripe.friend_prices.monthly.price_id', 'price_friend_monthly');

    $user = User::factory()->create();
    $createdPayload = stripeSubscriptionPayload($user, 'customer.subscription.created');
    app(StripeSubscriptionService::class)->synchronizeWebhook($createdPayload, stripeSignature($createdPayload));

    $updatedPayload = stripeSubscriptionPayload($user, 'customer.subscription.updated', [
        'cancel_at_period_end' => true,
        'canceled_at' => now()->timestamp,
    ]);
    $subscription = app(StripeSubscriptionService::class)->synchronizeWebhook($updatedPayload, stripeSignature($updatedPayload));

    expect($subscription)
        ->auto_renews->toBeFalse()
        ->cancelled_at->not->toBeNull();
    $this->assertDatabaseCount('subscriptions', 1);
});

/** @param array<string, mixed> $attributes */
function stripeSubscriptionPayload(User $user, string $eventType, array $attributes = []): string
{
    return json_encode([
        'id' => 'evt_friend_subscription',
        'object' => 'event',
        'type' => $eventType,
        'data' => [
            'object' => array_merge([
                'id' => 'sub_friend',
                'object' => 'subscription',
                'status' => 'active',
                'cancel_at_period_end' => false,
                'start_date' => now()->subMonth()->timestamp,
                'current_period_end' => now()->addMonth()->timestamp,
                'metadata' => ['user_id' => $user->id],
                'items' => [
                    'data' => [[
                        'price' => [
                            'id' => 'price_friend_monthly',
                            'unit_amount_decimal' => '420',
                            'currency' => 'eur',
                            'recurring' => [
                                'interval' => 'month',
                                'interval_count' => 1,
                            ],
                        ],
                    ]],
                ],
            ], $attributes),
        ],
    ], JSON_THROW_ON_ERROR);
}

function stripeSignature(string $payload): string
{
    $timestamp = now()->timestamp;
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_test');

    return "t={$timestamp},v1={$signature}";
}
