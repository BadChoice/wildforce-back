<?php

use App\Ai\Agents\Workouts\WorkoutPlanGeneratorAgent;
use App\Models\TrainingLocation;
use App\Models\TrainingPreference;
use App\Models\User;
use App\Services\Workouts\WorkoutPlanAIGenerator;

test('returns an unsaved workout plan generated from the client context', function () {
    $user = User::factory()->create();
    TrainingPreference::factory()->for($user)->create([
        'workout_days' => ['monday'],
        'goal' => 'buildMuscle',
        'general_training_level' => 'intermediate',
    ]);
    TrainingLocation::factory()->for($user)->create([
        'equipment' => ['bodyweight'],
    ]);
    WorkoutPlanGeneratorAgent::fake([[
        'name' => 'Full body foundation',
        'notes' => 'Build strength with controlled repetitions.',
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
                    'targetReps' => [12, 10, 8],
                    'targetWeightKg' => 20,
                    'targetWeightsKg' => [20, 22.5, 25],
                    'targetDurationMinutes' => 5,
                    'targetDurationSeconds' => 300,
                    'targetDistanceKm' => 1.5,
                    'targetPaceSecondsPerKm' => 300,
                    'restSeconds' => 90,
                    'setStyleConfiguration' => [
                        'style' => 'topSetBackoff',
                        'backoffSetCount' => 2,
                        'backoffWeightPercent' => 15,
                        'targetRIR' => 1,
                    ],
                ]],
            ]],
        ]],
    ]])->preventStrayPrompts();

    $plan = app(WorkoutPlanAIGenerator::class)->generate($user);

    expect($plan->exists)->toBeFalse()
        ->and($plan->id)->toBeNull()
        ->and($plan->user_id)->toBe($user->id)
        ->and($plan->goal)->toBe('buildMuscle')
        ->and($plan->mesocycle_number)->toBe(1)
        ->and($plan->phase)->toBe('accumulation')
        ->and($plan->workoutDays)->toHaveCount(1)
        ->and($plan->workoutDays->first()->blocks->first()->exercises->first()->exercise)->toBe('pushUp')
        ->and($plan->workoutDays->first()->blocks->first()->exercises->first()->target_reps)->toBe([12, 10, 8])
        ->and($plan->workoutDays->first()->blocks->first()->exercises->first()->target_weights_kg)->toBe([20, 22.5, 25])
        ->and($plan->workoutDays->first()->blocks->first()->exercises->first()->target_duration_minutes)->toBe(5)
        ->and($plan->workoutDays->first()->blocks->first()->exercises->first()->target_duration_seconds)->toBe(300)
        ->and($plan->workoutDays->first()->blocks->first()->exercises->first()->target_distance_km)->toBe('1.500')
        ->and($plan->workoutDays->first()->blocks->first()->exercises->first()->target_pace_seconds_per_km)->toBe(300)
        ->and($plan->workoutDays->first()->blocks->first()->exercises->first()->set_style_configuration)->toBe([
            'style' => 'topSetBackoff',
            'backoffSetCount' => 2,
            'backoffWeightPercent' => 15,
            'targetRIR' => 1,
        ]);

    $this->assertDatabaseCount('workout_plans', 0);
    $this->assertDatabaseCount('workout_days', 0);
    $this->assertDatabaseCount('workout_blocks', 0);
    $this->assertDatabaseCount('planned_exercises', 0);

    WorkoutPlanGeneratorAgent::assertPrompted(fn ($prompt): bool => $prompt
        ->contains('Goal: buildMuscle')
        && $prompt->contains('Next plan number: 1')
        && $prompt->contains('Recent workout history:')
        && $prompt->contains('targets:')
        && $prompt->contains('pushUp'));
});
