<?php

use App\Ai\Agents\Workouts\WorkoutPlanGeneratorAgent;
use App\Enums\SubscriptionStatus;
use App\Models\TrainingLocation;
use App\Models\TrainingPreference;
use App\Models\User;

test('it returns an unpersisted generated workout plan for the authenticated user', function () {
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

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/workout-plans/generate');

    $response->assertOk()
        ->assertJsonPath('data.name', 'Full body foundation')
        ->assertJsonPath('data.workout_days.0.blocks.0.exercises.0.set_style_configuration.style', 'straight')
        ->assertJsonPath('data.workout_days.0.blocks.0.exercises.0.set_style_configuration.target_rir', 2);

    $this->assertDatabaseCount('workout_plans', 0);
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
