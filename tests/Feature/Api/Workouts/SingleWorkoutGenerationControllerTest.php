<?php

use App\Ai\Agents\Workouts\SingleWorkoutAgent;
use App\Enums\SubscriptionStatus;
use App\Models\TrainingPreference;
use App\Models\User;

test('it returns a generated single workout without persisting it', function () {
    $user = User::factory()->create();
    TrainingPreference::factory()->for($user)->create([
        'goal' => 'buildMuscle',
        'general_training_level' => 'intermediate',
    ]);
    SingleWorkoutAgent::fake([singleWorkoutGenerationResponse()])->preventStrayPrompts();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/workout-days/generate', singleWorkoutRequest(), [
        'Idempotency-Key' => 'single-workout-generation-1',
    ]);

    $response->assertOk()
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.created_at')
        ->assertJsonMissingPath('data.blocks.0.id')
        ->assertJsonMissingPath('data.blocks.0.exercises.0.id')
        ->assertJsonPath('data.title', 'Full body express')
        ->assertJsonPath('data.blocks.0.exercises.0.exercise', 'pushUp')
        ->assertJsonPath('data.blocks.0.exercises.0.set_style_configuration.target_rir', 2);

    $this->assertDatabaseCount('workout_days', 0);
    $this->assertDatabaseCount('workout_blocks', 0);
    $this->assertDatabaseCount('planned_exercises', 0);

    SingleWorkoutAgent::assertPrompted(fn ($prompt): bool => $prompt
        ->contains('## Single workout request')
        && $prompt->contains('Target duration: 45 minutes total')
        && $prompt->contains('Available equipment for this session: bodyweight'));
});

test('it returns 422 when the single workout request is invalid', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->postJson('/api/workout-days/generate', [], [
        'Idempotency-Key' => 'single-workout-generation-invalid',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['focuses', 'muscleGroups', 'equipment', 'includeWarmup', 'includeCooldown', 'durationMinutes'])
        ->assertJsonPath('errors.durationMinutes.0', 'The duration minutes field is required.');
});

test('it returns 401 when no token is provided', function () {
    $this->postJson('/api/workout-days/generate', singleWorkoutRequest())
        ->assertUnauthorized();
});

test('it returns 403 when the user does not have app access', function () {
    $user = User::factory()->create();
    $user->subscription()->update(['status' => SubscriptionStatus::Expired]);

    $this->actingAs($user, 'sanctum')->postJson('/api/workout-days/generate', singleWorkoutRequest(), [
        'Idempotency-Key' => 'single-workout-generation-expired',
    ])->assertForbidden()
        ->assertJsonPath('code', 'subscription_required');
});

/** @return array<string, mixed> */
function singleWorkoutRequest(): array
{
    return [
        'goal' => 'buildMuscle',
        'focuses' => ['fullBody'],
        'muscleGroups' => ['chest'],
        'equipment' => ['bodyweight'],
        'includeWarmup' => true,
        'includeCooldown' => false,
        'durationMinutes' => 45,
    ];
}

/** @return array<string, mixed> */
function singleWorkoutGenerationResponse(): array
{
    return [
        'title' => 'Full body express',
        'focus' => 'fullBody',
        'dayType' => 'hypertrophy',
        'estimatedDurationMinutes' => 45,
        'blocks' => [[
            'type' => 'standard',
            'rounds' => 1,
            'exercises' => [[
                'exercise' => 'pushUp',
                'sets' => 3,
                'repsMin' => 8,
                'repsMax' => 12,
                'targetWeightKg' => 0,
                'restSeconds' => 90,
                'setStyleConfiguration' => [
                    'style' => 'straight',
                    'target_rir' => 2,
                ],
            ]],
        ]],
    ];
}
