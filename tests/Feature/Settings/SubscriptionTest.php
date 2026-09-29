<?php

use App\Enums\BillingInterval;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionProvider;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Stripe\StripeCustomerPortalService;
use Mockery\MockInterface;

test('subscription settings display the current Stripe subscription', function () {
    $user = User::factory()->create();
    $user->replaceSubscription(new Subscription([
        'plan' => SubscriptionPlan::Friends,
        'provider' => SubscriptionProvider::Stripe,
        'status' => SubscriptionStatus::Active,
        'auto_renews' => true,
        'billing_amount' => '4.200',
        'billing_currency' => 'EUR',
        'billing_interval' => BillingInterval::Month,
        'billing_interval_count' => 1,
        'provider_reference' => 'sub_friend',
        'starts_at' => now()->subMonth(),
        'renews_at' => now()->addMonth(),
    ]));

    $this->actingAs($user)
        ->get(route('subscription.edit'))
        ->assertSee('Subscription')
        ->assertSee('Friends')
        ->assertSee('4.200 EUR')
        ->assertSee('Manage with Stripe');
});

test('a Stripe subscriber can open the Stripe Customer Portal', function () {
    $user = User::factory()->create();
    $subscription = $user->replaceSubscription(new Subscription([
        'plan' => SubscriptionPlan::Friends,
        'provider' => SubscriptionProvider::Stripe,
        'status' => SubscriptionStatus::Active,
        'provider_reference' => 'sub_friend',
        'starts_at' => now()->subMonth(),
        'renews_at' => now()->addMonth(),
    ]));
    $this->mock(StripeCustomerPortalService::class, function (MockInterface $mock) use ($subscription): void {
        $mock->shouldReceive('create')
            ->once()
            ->withArgs(fn (Subscription $portalSubscription): bool => $portalSubscription->is($subscription))
            ->andReturn('https://billing.stripe.com/session');
    });

    $this->actingAs($user)
        ->post(route('subscription.stripe-portal'))
        ->assertRedirect('https://billing.stripe.com/session');
});

test('a non-Stripe subscriber cannot open the Stripe Customer Portal', function () {
    $user = User::factory()->create();

    $this->mock(StripeCustomerPortalService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('create');
    });

    $this->actingAs($user)
        ->post(route('subscription.stripe-portal'))
        ->assertNotFound();
});

test('the Stripe Customer Portal requires authentication', function () {
    $this->post(route('subscription.stripe-portal'))
        ->assertRedirect(route('login'));
});
