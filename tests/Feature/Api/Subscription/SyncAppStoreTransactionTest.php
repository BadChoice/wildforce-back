<?php

use App\Enums\BillingInterval;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionProvider;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AppStore\AppStoreJwsVerifier;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;

test('it stores a verified App Store subscription for the authenticated account', function () {
    $user = User::factory()->create();
    $transaction = appStoreTransaction($user->id);

    $this->mock(AppStoreJwsVerifier::class, function (MockInterface $mock) use ($transaction): void {
        $mock->shouldReceive('verify')->once()->with('signed-transaction')->andReturn($transaction);
    });
    Sanctum::actingAs($user);

    $this->postJson('/api/subscription/app-store/transactions', [
        'signed_transaction' => 'signed-transaction',
    ])
        ->assertOk()
        ->assertJsonPath('data.plan', 'premium')
        ->assertJsonPath('data.provider', 'app_store')
        ->assertJsonPath('data.status', 'active');

    expect($user->fresh()->subscription)
        ->plan->toBe(SubscriptionPlan::Premium)
        ->provider->toBe(SubscriptionProvider::AppStore)
        ->provider_reference->toBe('1000001234567890')
        ->auto_renews->toBeTrue()
        ->billing_amount->toBe('42.000')
        ->billing_currency->toBe('EUR')
        ->billing_interval->toBe(BillingInterval::Year)
        ->billing_interval_count->toBe(1);
});

test('it rejects a transaction already associated with another account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $otherUser->replaceSubscription(new Subscription([
        'plan' => SubscriptionPlan::Premium,
        'provider' => SubscriptionProvider::AppStore,
        'status' => SubscriptionStatus::Active,
        'provider_reference' => '1000001234567890',
        'starts_at' => now()->subMonth(),
        'renews_at' => now()->addYear(),
    ]));
    $transaction = appStoreTransaction((string) str()->uuid());

    $this->mock(AppStoreJwsVerifier::class, function (MockInterface $mock) use ($transaction): void {
        $mock->shouldReceive('verify')->once()->with('signed-transaction')->andReturn($transaction);
    });
    Sanctum::actingAs($user);

    $this->postJson('/api/subscription/app-store/transactions', [
        'signed_transaction' => 'signed-transaction',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('signed_transaction');
});

test('it requires authentication and a signed transaction', function () {
    $this->postJson('/api/subscription/app-store/transactions')
        ->assertUnauthorized();

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/subscription/app-store/transactions')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('signed_transaction');
});

/** @return array<string, mixed> */
function appStoreTransaction(string $appAccountToken): array
{
    return [
        'bundleId' => 'io.codepassion.doublegym',
        'type' => 'Auto-Renewable Subscription',
        'productId' => 'io.codepassion.wildforce.subscription.year',
        'originalTransactionId' => '1000001234567890',
        'appAccountToken' => $appAccountToken,
        'price' => 42000,
        'currency' => 'EUR',
        'purchaseDate' => now()->subMinute()->getTimestamp() * 1000,
        'expiresDate' => now()->addYear()->getTimestamp() * 1000,
    ];
}
