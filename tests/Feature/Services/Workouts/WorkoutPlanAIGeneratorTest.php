<?php

use App\Ai\Agents\Workouts\WorkoutPlanGeneratorAgent;
use App\Enums\WorkoutDayType;
use App\Models\ExerciseProfile;
use App\Models\ExerciseResult;
use App\Models\PlannedExercise;
use App\Models\TrainingLocation;
use App\Models\TrainingPreference;
use App\Models\User;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use App\Services\Workouts\WorkoutPlanAIGenerator;
use App\ValueObjects\Workouts\SetStyleConfiguration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

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
                        'backoff_set_count' => 2,
                        'backoff_weight_percent' => 15,
                        'target_rir' => 1,
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
        ->and($plan->workoutDays->first()->day_type)->toBe(WorkoutDayType::Hypertrophy)
        ->and($plan->workoutDays->first()->blocks->first()->exercises->first()->exercise)->toBe('pushUp')
        ->and($plan->workoutDays->first()->blocks->first()->exercises->first()->target_reps)->toBe([12, 10, 8])
        ->and($plan->workoutDays->first()->blocks->first()->exercises->first()->target_weights_kg)->toBe([20, 22.5, 25])
        ->and($plan->workoutDays->first()->blocks->first()->exercises->first()->target_duration_minutes)->toBe(5)
        ->and($plan->workoutDays->first()->blocks->first()->exercises->first()->target_duration_seconds)->toBe(300)
        ->and($plan->workoutDays->first()->blocks->first()->exercises->first()->target_distance_km)->toBe('1.500')
        ->and($plan->workoutDays->first()->blocks->first()->exercises->first()->target_pace_seconds_per_km)->toBe(300)
        ->and($plan->workoutDays->first()->blocks->first()->exercises->first()->set_style_configuration)->toBeInstanceOf(SetStyleConfiguration::class)
        ->and($plan->workoutDays->first()->blocks->first()->exercises->first()->set_style_configuration->toArray())->toBe([
            'style' => 'topSetBackoff',
            'backoff_set_count' => 2,
            'backoff_weight_percent' => 15.0,
            'target_rir' => 1,
        ]);

    $this->assertDatabaseCount('workout_plans', 0);
    $this->assertDatabaseCount('workout_days', 0);
    $this->assertDatabaseCount('workout_blocks', 0);
    $this->assertDatabaseCount('planned_exercises', 0);

    WorkoutPlanGeneratorAgent::assertPrompted(fn ($prompt): bool => $prompt
        ->contains('Goal: buildMuscle')
        && $prompt->contains('Next plan number: 1')
        && $prompt->contains('## Recent active workout performance')
        && $prompt->contains('targets:')
        && $prompt->contains('pushUp'));
});

test('returns the generation prompt and response schema without prompting the AI', function () {
    $user = User::factory()->create();
    TrainingPreference::factory()->for($user)->create([
        'workout_days' => ['monday'],
        'goal' => 'buildMuscle',
    ]);
    TrainingLocation::factory()->for($user)->create([
        'equipment' => ['bodyweight'],
    ]);
    ExerciseProfile::factory()->for($user)->create([
        'exercise' => 'pushUp',
        'max_reps' => 35,
    ]);
    WorkoutPlanGeneratorAgent::fake()->preventStrayPrompts();

    $preview = app(WorkoutPlanAIGenerator::class)->preview($user);
    $schema = json_decode($preview['schema'], true, flags: JSON_THROW_ON_ERROR);

    expect($preview['instructions'])->toContain('expert strength and conditioning coach')
        ->and($preview['instructions'])->toContain('Follow these priorities, in order:')
        ->and($preview['instructions'])->toContain('Maintaining the same load and reps is a valid prescription')
        ->and($preview['instructions'])->toContain('include `setStyleConfiguration` with a `style` and appropriate `targetRIR`')
        ->and($preview['prompt'])->toContain('## Client context')
        ->and($preview['prompt'])->toContain('Goal: buildMuscle')
        ->and($preview['prompt'])->toContain('historical max reps: 35')
        ->and($preview['prompt'])->toContain('## Allowed exercises')
        ->and($preview['prompt'])->toContain('```text')
        ->and($schema)->toHaveKey('properties.workoutDays')
        ->and($schema['properties']['workoutDays']['minItems'])->toBe(1)
        ->and($schema['properties']['workoutDays']['maxItems'])->toBe(1)
        ->and($schema['properties']['workoutDays']['items']['properties']['blocks']['items']['properties']['exercises']['items']['properties']['setStyleConfiguration']['properties'])->toHaveKey('target_rir')
        ->and($schema['additionalProperties'])->toBeFalse();

    WorkoutPlanGeneratorAgent::assertNeverPrompted();
});

test('separates deload performance from the active prescription baseline', function () {
    $user = User::factory()->create();
    TrainingPreference::factory()->for($user)->create([
        'workout_days' => ['monday'],
        'goal' => 'buildMuscle',
    ]);
    TrainingLocation::factory()->for($user)->create([
        'equipment' => ['bodyweight'],
    ]);
    createWorkoutPlanGeneratorHistory($user, 'accumulation', '2026-01-01', 35);
    createWorkoutPlanGeneratorHistory($user, 'deload', '2026-01-08', 20);
    WorkoutPlanGeneratorAgent::fake()->preventStrayPrompts();

    $preview = app(WorkoutPlanAIGenerator::class)->preview($user);
    $activePerformance = (string) str($preview['prompt'])
        ->after('## Recent active workout performance')
        ->before('## Recent deload history');
    $deloadHistory = (string) str($preview['prompt'])
        ->after('## Recent deload history')
        ->before('## Allowed exercises');

    expect($activePerformance)->toContain('35.00 kg')
        ->not->toContain('20.00 kg')
        ->and($deloadHistory)->toContain('20.00 kg')
        ->not->toContain('35.00 kg');

    WorkoutPlanGeneratorAgent::assertNeverPrompted();
});

test('requires a set style configuration with target RIR for intermediate resistance work', function () {
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
                ]],
            ]],
        ]],
    ]])->preventStrayPrompts();

    app(WorkoutPlanAIGenerator::class)->generate($user);
})->throws(RuntimeException::class, 'Workout plan response requires a set-style configuration with target RIR for pushUp.');

function createWorkoutPlanGeneratorHistory(User $user, string $phase, string $date, float $weight): void
{
    $completedAt = Carbon::parse($date);
    $plan = WorkoutPlan::factory()->for($user)->create([
        'phase' => $phase,
        'created_at' => $completedAt,
        'updated_at' => $completedAt,
    ]);
    $workoutDay = WorkoutDay::factory()->for($user)->for($plan, 'plan')->create([
        'status' => 'completed',
        'created_at' => $completedAt,
        'updated_at' => $completedAt,
    ]);
    $plannedExercise = PlannedExercise::factory()->for($workoutDay, 'workoutDay')->create([
        'exercise' => 'pushUp',
        'created_at' => $completedAt,
        'updated_at' => $completedAt,
    ]);

    ExerciseResult::query()->forceCreate([
        'id' => (string) Str::uuid(),
        'planned_exercise_id' => $plannedExercise->id,
        'feedback' => 'justRight',
        'completed_at' => $completedAt,
        'completed_sets' => 3,
        'completed_reps' => 10,
        'completed_weight' => $weight,
        'created_at' => $completedAt,
        'updated_at' => $completedAt,
    ]);
}
