<?php

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionProvider;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AppStore\AppStoreJwsVerifier;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;

test('it applies an App Store renewal notification to the linked subscription', function () {
    $user = User::factory()->create();
    $user->replaceSubscription(new Subscription([
        'plan' => SubscriptionPlan::MemberYearly,
        'provider' => SubscriptionProvider::AppStore,
        'status' => SubscriptionStatus::Active,
        'auto_renews' => true,
        'provider_reference' => '1000001234567890',
        'starts_at' => now()->subYear(),
        'renews_at' => now()->subSecond(),
    ]));
    $transaction = webhookTransaction($user->id);

    $this->mock(AppStoreJwsVerifier::class, function (MockInterface $mock) use ($transaction): void {
        $mock->shouldReceive('verify')->once()->with('notification-jws')->andReturn([
            'notificationType' => 'DID_RENEW',
            'data' => [
                'signedTransactionInfo' => 'transaction-jws',
                'signedRenewalInfo' => 'renewal-jws',
            ],
        ]);
        $mock->shouldReceive('verify')->once()->with('transaction-jws')->andReturn($transaction);
        $mock->shouldReceive('verify')->once()->with('renewal-jws')->andReturn(['autoRenewStatus' => 1]);
    });

    $this->postJson('/api/webhooks/app-store', ['signedPayload' => 'notification-jws'])
        ->assertNoContent();

    expect($user->fresh()->subscription)
        ->status->toBe(SubscriptionStatus::Active)
        ->auto_renews->toBeTrue()
        ->renews_at->toEqual(now()->addYear()->startOfSecond());
});

test('it marks a subscription to end when App Store disables renewal', function () {
    $user = User::factory()->create();
    $user->replaceSubscription(new Subscription([
        'plan' => SubscriptionPlan::MemberYearly,
        'provider' => SubscriptionProvider::AppStore,
        'status' => SubscriptionStatus::Active,
        'auto_renews' => true,
        'provider_reference' => '1000001234567890',
        'starts_at' => now()->subMonth(),
        'renews_at' => now()->addYear(),
    ]));
    $transaction = webhookTransaction($user->id);

    $this->mock(AppStoreJwsVerifier::class, function (MockInterface $mock) use ($transaction): void {
        $mock->shouldReceive('verify')->once()->with('notification-jws')->andReturn([
            'notificationType' => 'DID_CHANGE_RENEWAL_STATUS',
            'data' => [
                'signedTransactionInfo' => 'transaction-jws',
                'signedRenewalInfo' => 'renewal-jws',
            ],
        ]);
        $mock->shouldReceive('verify')->once()->with('transaction-jws')->andReturn($transaction);
        $mock->shouldReceive('verify')->once()->with('renewal-jws')->andReturn(['autoRenewStatus' => 0]);
    });

    $this->postJson('/api/webhooks/app-store', ['signedPayload' => 'notification-jws'])
        ->assertNoContent();

    expect($user->fresh()->subscription)
        ->status->toBe(SubscriptionStatus::Active)
        ->auto_renews->toBeFalse()
        ->cancelled_at->not->toBeNull();
});

test('it rejects malformed App Store notifications', function () {
    $this->postJson('/api/webhooks/app-store', [])
        ->assertBadRequest();

    $this->mock(AppStoreJwsVerifier::class, function (MockInterface $mock): void {
        $mock->shouldReceive('verify')->once()->with('invalid-jws')->andThrow(
            ValidationException::withMessages(['signed_transaction' => 'Invalid'])
        );
    });

    $this->postJson('/api/webhooks/app-store', ['signedPayload' => 'invalid-jws'])
        ->assertBadRequest();
});

/** @return array<string, mixed> */
function webhookTransaction(string $appAccountToken): array
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
