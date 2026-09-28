<?php

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionProvider;
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
        ->assertJsonPath('data.plan', 'member_yearly')
        ->assertJsonPath('data.provider', 'app_store')
        ->assertJsonPath('data.status', 'active');

    expect($user->fresh()->subscription)
        ->plan->toBe(SubscriptionPlan::MemberYearly)
        ->provider->toBe(SubscriptionProvider::AppStore)
        ->provider_reference->toBe('1000001234567890')
        ->auto_renews->toBeTrue();
});

test('it rejects a transaction that is not linked to the authenticated account', function () {
    $user = User::factory()->create();
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
        'purchaseDate' => now()->subMinute()->getTimestamp() * 1000,
        'expiresDate' => now()->addYear()->getTimestamp() * 1000,
    ];
}
