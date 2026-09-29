<?php

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
