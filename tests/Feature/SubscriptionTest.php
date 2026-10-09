<?php

use App\Enums\CoachClientLimit;
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

test('a user is a coach with an active coach subscription', function () {
    $coach = User::factory()->create();
    $coach->replaceSubscription(Subscription::createCoachTrial());

    expect($coach->isCoach())->toBeTrue()
        ->and($coach->subscription->plan)->toBe(SubscriptionPlan::CoachTrial);
});

test('a coach subscription receives its plan client limit when created', function (SubscriptionPlan $plan, ?int $expectedLimit) {
    $user = User::factory()->create();

    $subscription = $user->replaceSubscription(Subscription::factory()->make([
        'user_id' => null,
        'plan' => $plan,
    ]));

    expect($subscription->active_client_limit)->toBe($expectedLimit);
})->with([
    'basic' => [SubscriptionPlan::CoachBasic, CoachClientLimit::Five->limit()],
    'studio' => [SubscriptionPlan::CoachStudio, CoachClientLimit::Thirty->limit()],
    'pro' => [SubscriptionPlan::CoachPro, CoachClientLimit::Unlimited->limit()],
]);

test('a coach subscription client limit can be customized', function () {
    $user = User::factory()->create();
    $subscription = $user->replaceSubscription(Subscription::factory()->make([
        'user_id' => null,
        'plan' => SubscriptionPlan::CoachBasic,
    ]));

    $subscription->update(['active_client_limit' => 12]);

    expect($subscription->fresh()->active_client_limit)->toBe(12);
});

test('a user with clients is not a coach without an active coach subscription', function () {
    $coach = User::factory()->create();
    $client = User::factory()->create();
    $coach->subscription()->update(['status' => SubscriptionStatus::Expired]);

    CoachingEnrollment::factory()->create([
        'coach_user_id' => $coach->id,
        'client_user_id' => $client->id,
    ]);

    expect($coach->isCoach())->toBeFalse();
});

test('a user cannot attach a second subscription', function () {
    $user = User::factory()->create();

    expect(fn () => $user->attachSubscription(Subscription::createTrial()))
        ->toThrow(LogicException::class);
});
