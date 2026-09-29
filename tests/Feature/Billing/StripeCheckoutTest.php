<?php

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionProvider;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Stripe\StripeCheckoutService;
use Mockery\MockInterface;

test('it redirects an authenticated user to the monthly Stripe Checkout', function () {
    $user = User::factory()->create();
    $this->mock(StripeCheckoutService::class, function (MockInterface $mock) use ($user): void {
        $mock->shouldReceive('create')
            ->once()
            ->with($user, 'monthly')
            ->andReturn('https://checkout.stripe.com/pay/monthly');
    });

    $response = $this->actingAs($user)->post(route('billing.checkout', ['interval' => 'monthly']));

    $response->assertRedirect('https://checkout.stripe.com/pay/monthly');
});

test('it displays monthly and yearly Checkout options', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('billing.index'))
        ->assertSee('Continue monthly')
        ->assertSee('Continue yearly');
});

test('it shows an existing subscription and prevents another Stripe Checkout', function () {
    $user = User::factory()->create();
    $user->replaceSubscription(new Subscription([
        'plan' => SubscriptionPlan::MemberMonthly,
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
        ->assertSee('Member Monthly')
        ->assertDontSee('Continue monthly')
        ->assertDontSee('Continue yearly');

    $this->actingAs($user)
        ->post(route('billing.checkout', ['interval' => 'monthly']))
        ->assertRedirectToRoute('billing.index');
});

test('it redirects an authenticated user to the yearly Stripe Checkout', function () {
    $user = User::factory()->create();
    $this->mock(StripeCheckoutService::class, function (MockInterface $mock) use ($user): void {
        $mock->shouldReceive('create')
            ->once()
            ->with($user, 'yearly')
            ->andReturn('https://checkout.stripe.com/pay/yearly');
    });

    $response = $this->actingAs($user)->post(route('billing.checkout', ['interval' => 'yearly']));

    $response->assertRedirect('https://checkout.stripe.com/pay/yearly');
});

test('it does not expose Checkout for unsupported billing intervals', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/billing/checkout/weekly')
        ->assertNotFound();
});
