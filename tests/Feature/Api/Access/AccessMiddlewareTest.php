<?php

use App\Enums\CoachingEnrollmentStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionProvider;
use App\Enums\SubscriptionStatus;
use App\Models\CoachingEnrollment;
use App\Models\Subscription;
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

test('it grants app access to a client with an active coach subscription', function () {
    $client = User::factory()->create();
    $client->subscription()->update(['status' => SubscriptionStatus::Expired]);

    $coach = User::factory()->create();
    $coach->replaceSubscription(new Subscription([
        'plan' => SubscriptionPlan::CoachBasicMonthly,
        'provider' => SubscriptionProvider::Stripe,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now()->subDay(),
        'renews_at' => now()->addMonth(),
    ]));

    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);

    Sanctum::actingAs($client);

    $this->getJson('/api/sync/pull?resource=training-locations')
        ->assertOk();

    $this->getJson('/api/subscription/access')
        ->assertOk()
        ->assertJsonPath('data.has_access', true)
        ->assertJsonPath('data.status', SubscriptionStatus::Expired->value);
});

test('it does not grant app access when the coach subscription is inactive', function () {
    $client = User::factory()->create();
    $client->subscription()->update(['status' => SubscriptionStatus::Expired]);

    $coach = User::factory()->create();
    $coach->replaceSubscription(Subscription::createCoachTrial());
    $coach->subscription()->update(['status' => SubscriptionStatus::Expired]);

    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);

    Sanctum::actingAs($client);

    $this->getJson('/api/sync/pull?resource=workout_plans')
        ->assertForbidden()
        ->assertJsonPath('code', 'subscription_required');
});

test('it requires authentication to read subscription access status', function () {
    $this->getJson('/api/subscription/access')
        ->assertUnauthorized();
});
