<?php

use App\Enums\SubscriptionStatus;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('it blocks sync access when the user has no subscription, trial, or demo access', function () {
    $user = User::factory()->create();
    $user->subscription()->update(['status' => SubscriptionStatus::Expired]);

    Sanctum::actingAs($user);

    $this->getJson('/api/sync/pull?resource=workout_plans')
        ->assertForbidden()
        ->assertJsonPath('code', 'subscription_required');
});

test('it returns subscription access status for an expired user', function () {
    $user = User::factory()->create();
    $user->subscription()->update(['status' => SubscriptionStatus::Expired]);

    Sanctum::actingAs($user);

    $this->getJson('/api/subscription/access')
        ->assertOk()
        ->assertJsonPath('data.has_access', false)
        ->assertJsonPath('data.status', SubscriptionStatus::Expired->value)
        ->assertJsonPath('data.plan', 'trial');
});

test('it requires authentication to read subscription access status', function () {
    $this->getJson('/api/subscription/access')
        ->assertUnauthorized();
});
