<?php

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionProvider;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Stripe\StripeCheckoutService;
use Mockery\MockInterface;

test('it redirects an authenticated user to the Friends monthly Stripe Checkout', function () {
    $user = User::factory()->create();
    $this->mock(StripeCheckoutService::class, function (MockInterface $mock) use ($user): void {
        $mock->shouldReceive('create')
            ->once()
            ->with($user, SubscriptionPlan::Friends, 'monthly')
            ->andReturn('https://checkout.stripe.com/pay/monthly');
    });

    $response = $this->actingAs($user)->post(route('billing.checkout', ['plan' => 'friends', 'interval' => 'monthly']));

    $response->assertRedirect('https://checkout.stripe.com/pay/monthly');
});

test('it displays the configured Friends Checkout options', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('billing.index'))
        ->assertSee('Friends')
        ->assertSee('Continue Monthly')
        ->assertSee('Continue Yearly');
});

test('it allows a demo subscription to start Stripe Checkout', function () {
    $user = User::factory()->create();
    $user->replaceSubscription(new Subscription([
        'plan' => SubscriptionPlan::Demo,
        'provider' => SubscriptionProvider::Internal,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now()->subDay(),
        'renews_at' => now()->addDay(),
    ]));
    $this->mock(StripeCheckoutService::class, function (MockInterface $mock) use ($user): void {
        $mock->shouldReceive('create')
            ->once()
            ->with($user, SubscriptionPlan::Friends, 'monthly')
            ->andReturn('https://checkout.stripe.com/pay/monthly');
    });

    $this->actingAs($user)
        ->get(route('billing.index'))
        ->assertSee('Friends')
        ->assertSee('Continue Monthly')
        ->assertSee('Continue Yearly');

    $this->actingAs($user)
        ->post(route('billing.checkout', ['plan' => 'friends', 'interval' => 'monthly']))
        ->assertRedirect('https://checkout.stripe.com/pay/monthly');
});

test('it shows an existing subscription and prevents another Stripe Checkout', function () {
    $user = User::factory()->create();
    $user->replaceSubscription(new Subscription([
        'plan' => SubscriptionPlan::Friends,
        'provider' => SubscriptionProvider::Stripe,
        'status' => SubscriptionStatus::Active,
        'auto_renews' => true,
        'provider_reference' => 'sub_friend',
        'starts_at' => now()->subMonth(),
        'renews_at' => now()->addMonth(),
    ]));
    $this->mock(StripeCheckoutService::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('create');
    });

    $this->actingAs($user)
        ->get(route('billing.index'))
        ->assertSee('You already have a subscription.')
        ->assertSee('Friends')
        ->assertDontSee('Continue Monthly')
        ->assertDontSee('Continue Yearly');

    $this->actingAs($user)
        ->post(route('billing.checkout', ['plan' => 'friends', 'interval' => 'monthly']))
        ->assertRedirectToRoute('billing.index');
});

test('it redirects an authenticated user to the Friends yearly Stripe Checkout', function () {
    $user = User::factory()->create();
    $this->mock(StripeCheckoutService::class, function (MockInterface $mock) use ($user): void {
        $mock->shouldReceive('create')
            ->once()
            ->with($user, SubscriptionPlan::Friends, 'yearly')
            ->andReturn('https://checkout.stripe.com/pay/yearly');
    });

    $response = $this->actingAs($user)->post(route('billing.checkout', ['plan' => 'friends', 'interval' => 'yearly']));

    $response->assertRedirect('https://checkout.stripe.com/pay/yearly');
});

test('it does not expose Checkout for unsupported billing intervals', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/billing/checkout/friends/weekly')
        ->assertNotFound();
});

test('it does not expose Checkout for plans without a configured Stripe price', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/billing/checkout/premium/monthly')
        ->assertNotFound();
});
