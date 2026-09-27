<?php

use App\Enums\CoachingEnrollmentStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionProvider;
use App\Enums\SubscriptionStatus;
use App\Models\CoachingEnrollment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('coaches and clients are related through coaching enrollments', function () {
    $client = User::factory()->create();
    $coach = User::factory()->create();

    $enrollment = CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);

    $subscription = $client->replaceSubscription(new Subscription([
        'plan' => SubscriptionPlan::CoachedExternal,
        'provider' => SubscriptionProvider::External,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now(),
    ]));

    $coachClient = $coach->clients()->sole();
    $clientCoach = $client->coaches()->sole();

    expect($client->subscription->id)->toBe($subscription->id)
        ->and($coachClient->id)->toBe($client->id)
        ->and($coachClient->enrollment->id)->toBe($enrollment->id)
        ->and($coachClient->enrollment->status)->toBe(CoachingEnrollmentStatus::Active)
        ->and($clientCoach->id)->toBe($coach->id)
        ->and($clientCoach->enrollment->id)->toBe($enrollment->id)
        ->and($subscription->plan)->toBe(SubscriptionPlan::CoachedExternal)
        ->and($subscription->provider)->toBe(SubscriptionProvider::External)
        ->and($subscription->status)->toBe(SubscriptionStatus::Active);
});

test('a user cannot attach a second subscription', function () {
    $user = User::factory()->create();

    expect(fn () => $user->attachSubscription(Subscription::createTrial()))
        ->toThrow(LogicException::class);
});
