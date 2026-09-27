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
