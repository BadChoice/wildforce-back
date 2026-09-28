<?php

use App\Enums\CoachingEnrollmentStatus;
use App\Models\BodyMetricEntry;
use App\Models\CoachingEnrollment;
use App\Models\ExerciseProfile;
use App\Models\NutritionPlan;
use App\Models\User;
use App\Models\WorkoutPlan;
use Illuminate\Database\Eloquent\Model;

test('users, their active coaches, and administrators can view personal data', function (string $modelClass) {
    $client = User::factory()->create();
    $coach = User::factory()->create();
    $admin = User::factory()->admin()->create();

    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);

    $record = userOwnedRecord($modelClass, $client);

    expect($client->can('view', $record))->toBeTrue()
        ->and($coach->can('view', $record))->toBeTrue()
        ->and($admin->can('view', $record))->toBeTrue();
})->with([
    WorkoutPlan::class,
    NutritionPlan::class,
    BodyMetricEntry::class,
    ExerciseProfile::class,
]);

test('other users and inactive coaches cannot view personal data', function (string $modelClass) {
    $client = User::factory()->create();
    $formerCoach = User::factory()->create();
    $otherUser = User::factory()->create();

    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $formerCoach->id,
        'status' => CoachingEnrollmentStatus::Ended,
        'starts_at' => now()->subMonth(),
        'ends_at' => now(),
    ]);

    $record = userOwnedRecord($modelClass, $client);

    expect($formerCoach->can('view', $record))->toBeFalse()
        ->and($otherUser->can('view', $record))->toBeFalse();
})->with([
    WorkoutPlan::class,
    NutritionPlan::class,
    BodyMetricEntry::class,
    ExerciseProfile::class,
]);

test('clients can be filtered by their coaching enrollment status', function () {
    $coach = User::factory()->create();
    $activeClient = User::factory()->create();
    $endedClient = User::factory()->create();

    CoachingEnrollment::create([
        'client_user_id' => $activeClient->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);
    CoachingEnrollment::create([
        'client_user_id' => $endedClient->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Ended,
        'starts_at' => now()->subMonth(),
        'ends_at' => now(),
    ]);

    expect($coach->clients()->get()->modelKeys())->toEqualCanonicalizing([$activeClient->id, $endedClient->id])
        ->and($coach->clients(CoachingEnrollmentStatus::Active)->get()->modelKeys())->toBe([$activeClient->id]);
});

/**
 * @param  class-string<Model>  $modelClass
 */
function userOwnedRecord(string $modelClass, User $user): Model
{
    $record = new $modelClass;
    $record->forceFill(['user_id' => $user->id]);

    return $record;
}
