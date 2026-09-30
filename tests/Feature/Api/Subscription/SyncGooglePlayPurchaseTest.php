<?php

use App\Enums\BillingInterval;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionProvider;
use App\Models\User;
use App\Services\GooglePlay\GooglePlayPublisherClient;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;

test('it stores a Google Play subscription verified by the publisher API', function () {
    $user = User::factory()->create();
    $accountId = hash('sha256', $user->id);
    $purchase = googlePlayPurchase($accountId);
    $this->mock(GooglePlayPublisherClient::class, function (MockInterface $mock) use ($purchase): void {
        $mock->shouldReceive('subscriptionPurchase')->once()->with('purchase-token')->andReturn($purchase);
    });
    Sanctum::actingAs($user);

    $this->postJson('/api/subscription/google-play/purchases', [
        'purchase_token' => 'purchase-token',
        'obfuscated_account_id' => $accountId,
    ])->assertOk()
        ->assertJsonPath('data.provider', 'google_play')
        ->assertJsonPath('data.status', 'active');

    expect($user->fresh()->subscription)
        ->plan->toBe(SubscriptionPlan::Premium)
        ->provider->toBe(SubscriptionProvider::GooglePlay)
        ->provider_reference->toBe('purchase-token')
        ->auto_renews->toBeTrue()
        ->billing_interval->toBe(BillingInterval::Year)
        ->billing_interval_count->toBe(1);
});

test('it rejects a Google Play purchase for another application account', function () {
    $user = User::factory()->create();
    $accountId = hash('sha256', $user->id);
    $this->mock(GooglePlayPublisherClient::class, function (MockInterface $mock): void {
        $mock->shouldReceive('subscriptionPurchase')->once()->andReturn(googlePlayPurchase(hash('sha256', 'other-account')));
    });
    Sanctum::actingAs($user);

    $this->postJson('/api/subscription/google-play/purchases', [
        'purchase_token' => 'purchase-token',
        'obfuscated_account_id' => $accountId,
    ])->assertUnprocessable()->assertJsonValidationErrors('purchase_token');
});

/** @return array<string, mixed> */
function googlePlayPurchase(string $accountId): array
{
    return [
        'startTime' => now()->subMinute()->toISOString(),
        'subscriptionState' => 'SUBSCRIPTION_STATE_ACTIVE',
        'externalAccountIdentifiers' => ['obfuscatedExternalAccountId' => $accountId],
        'lineItems' => [[
            'productId' => 'io.codepassion.wildforce.subscription.year',
            'expiryTime' => now()->addYear()->toISOString(),
            'autoRenewingPlan' => ['autoRenewEnabled' => true],
        ]],
    ];
}
