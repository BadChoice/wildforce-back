<?php

use App\Ai\Agents\Workouts\WorkoutPlanGeneratorAgent;
use App\Enums\SubscriptionStatus;
use App\Models\TrainingLocation;
use App\Models\TrainingPreference;
use App\Models\User;

test('it persists and returns a generated workout plan using the sync payload', function () {
    $user = User::factory()->create();
    TrainingPreference::factory()->for($user)->create([
        'workout_days' => ['monday'],
        'goal' => 'buildMuscle',
        'general_training_level' => 'intermediate',
    ]);
    TrainingLocation::factory()->for($user)->create([
        'equipment' => ['bodyweight'],
    ]);
    WorkoutPlanGeneratorAgent::fake([workoutPlanGenerationResponse()])->preventStrayPrompts();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/workout-plans/generate', [], [
        'Idempotency-Key' => 'workout-plan-generation-1',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.id', fn (string $id): bool => $id !== '')
        ->assertJsonPath('data.name', 'Full body foundation')
        ->assertJsonPath('data.workout_days.0.blocks.0.exercises.0.set_style_configuration.style', 'straight')
        ->assertJsonPath('data.workout_days.0.blocks.0.exercises.0.set_style_configuration.target_rir', 2);

    $this->assertDatabaseCount('workout_plans', 1);
    $this->assertDatabaseCount('workout_days', 1);
    $this->assertDatabaseCount('workout_blocks', 1);
    $this->assertDatabaseCount('planned_exercises', 1);
});

test('it returns the original response when an idempotency key is retried', function () {
    $user = User::factory()->create();
    TrainingPreference::factory()->for($user)->create(['workout_days' => ['monday']]);
    TrainingLocation::factory()->for($user)->create(['equipment' => ['bodyweight']]);
    WorkoutPlanGeneratorAgent::fake([workoutPlanGenerationResponse()])->preventStrayPrompts();

    $headers = ['Idempotency-Key' => 'workout-plan-generation-retry'];
    $first = $this->actingAs($user, 'sanctum')->postJson('/api/workout-plans/generate', [], $headers);
    $second = $this->actingAs($user, 'sanctum')->postJson('/api/workout-plans/generate', [], $headers);

    $first->assertCreated();
    $second->assertCreated()
        ->assertExactJson($first->json());

    $this->assertDatabaseCount('workout_plans', 1);
});

test('it requires an idempotency key', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->postJson('/api/workout-plans/generate')
        ->assertBadRequest()
        ->assertJsonPath('code', 'idempotency_key_required');
});

test('it returns 401 when no token is provided', function () {
    $this->postJson('/api/workout-plans/generate')
        ->assertUnauthorized();
});

test('it returns 403 when the user does not have app access', function () {
    $user = User::factory()->create();
    $user->subscription()->update(['status' => SubscriptionStatus::Expired]);

    $this->actingAs($user, 'sanctum')->postJson('/api/workout-plans/generate')
        ->assertForbidden()
        ->assertJsonPath('code', 'subscription_required');
});

/**
 * @return array<string, mixed>
 */
function workoutPlanGenerationResponse(): array
{
    return [
        'name' => 'Full body foundation',
        'workoutDays' => [[
            'title' => 'Full body',
            'focus' => 'fullBody',
            'intendedWeekday' => 'monday',
            'dayType' => 'hypertrophy',
            'estimatedDurationMinutes' => 50,
            'blocks' => [[
                'type' => 'standard',
                'rounds' => 1,
                'exercises' => [[
                    'exercise' => 'pushUp',
                    'sets' => 3,
                    'repsMin' => 8,
                    'repsMax' => 12,
                    'setStyleConfiguration' => [
                        'style' => 'straight',
                        'target_rir' => 2,
                    ],
                ]],
            ]],
        ]],
    ];
}
