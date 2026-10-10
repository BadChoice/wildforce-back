<?php

use App\Ai\Agents\Workouts\AdaptWorkoutDayLocationAgent;
use App\Enums\Equipment;
use App\Enums\WorkoutBlockType;
use App\Models\PlannedExercise;
use App\Models\TrainingLocation;
use App\Models\TrainingPreference;
use App\Models\User;
use App\Models\WorkoutBlock;
use App\Models\WorkoutDay;

test('it returns an adapted workout day without changing the original', function () {
    $user = User::factory()->create();
    TrainingPreference::factory()->for($user)->create(['general_training_level' => 'beginner']);
    $workoutDay = WorkoutDay::factory()->for($user)->create([
        'title' => 'Push day',
        'focus' => 'upperBody',
        'estimated_duration_minutes' => 40,
    ]);
    $block = WorkoutBlock::factory()->for($workoutDay, 'workoutDay')->create(['type' => WorkoutBlockType::Standard]);
    PlannedExercise::factory()->for($workoutDay, 'workoutDay')->for($block, 'block')->create(['exercise' => 'barbellBenchPress']);
    $hotelGym = TrainingLocation::factory()->for($user)->create([
        'name' => 'Hotel gym',
        'equipment' => [Equipment::Dumbbells, Equipment::FlatBench],
    ]);
    AdaptWorkoutDayLocationAgent::fake([adaptedWorkoutDayResponse()])->preventStrayPrompts();

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/workout-days/{$workoutDay->id}/adapt-location", [
        'training_location_id' => $hotelGym->id,
    ], ['Idempotency-Key' => 'adapt-workout-day-location-1']);

    $response->assertOk()
        ->assertJsonMissingPath('data.id')
        ->assertJsonPath('data.title', 'Hotel push day')
        ->assertJsonPath('data.blocks.0.exercises.0.exercise', 'dumbbellBenchPress');

    expect($workoutDay->fresh()->title)->toBe('Push day');
    $this->assertDatabaseCount('workout_days', 1);
    $this->assertDatabaseHas('planned_exercises', ['exercise' => 'barbellBenchPress']);
    $this->assertDatabaseCount('planned_exercises', 1);

    AdaptWorkoutDayLocationAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('training location "Hotel gym"')
        && $prompt->contains('standard | barbellBenchPress')
        && $prompt->contains('Target duration: 40 minutes total')
        && $prompt->contains('Include warmup: No')
        && $prompt->contains('Available equipment for this session: dumbbells, flatBench')
        && $prompt->contains('dumbbellBenchPress |')
        && ! $prompt->contains("\nbarbellBenchPress |"));
});

test('it rejects a training location that belongs to another user', function () {
    $user = User::factory()->create();
    $workoutDay = WorkoutDay::factory()->for($user)->create();
    $otherLocation = TrainingLocation::factory()->create();
    AdaptWorkoutDayLocationAgent::fake()->preventStrayPrompts();

    $this->actingAs($user, 'sanctum')->postJson("/api/workout-days/{$workoutDay->id}/adapt-location", [
        'training_location_id' => $otherLocation->id,
    ], ['Idempotency-Key' => 'adapt-workout-day-location-foreign'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['training_location_id']);
});

test('it forbids adapting a workout day of another user', function () {
    $owner = User::factory()->create();
    $workoutDay = WorkoutDay::factory()->for($owner)->create();
    $location = TrainingLocation::factory()->for($owner)->create();
    AdaptWorkoutDayLocationAgent::fake()->preventStrayPrompts();

    $this->actingAs(User::factory()->create(), 'sanctum')->postJson("/api/workout-days/{$workoutDay->id}/adapt-location", [
        'training_location_id' => $location->id,
    ], ['Idempotency-Key' => 'adapt-workout-day-location-forbidden'])
        ->assertForbidden();
});

/** @return array<string, mixed> */
function adaptedWorkoutDayResponse(): array
{
    return [
        'title' => 'Hotel push day',
        'focus' => 'upperBody',
        'dayType' => 'hypertrophy',
        'estimatedDurationMinutes' => 40,
        'blocks' => [[
            'type' => 'standard',
            'rounds' => 1,
            'exercises' => [[
                'exercise' => 'dumbbellBenchPress',
                'sets' => 3,
                'repsMin' => 8,
                'repsMax' => 12,
                'targetWeightKg' => 20,
                'restSeconds' => 90,
            ]],
        ]],
    ];
}
