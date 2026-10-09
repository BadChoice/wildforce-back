<?php

use App\Enums\ExerciseStatus;
use App\Models\ExerciseResult;
use App\Models\PlannedExercise;
use App\Models\User;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use App\Services\ExerciseCatalog\ExerciseCatalog;
use App\Services\Workouts\Progression\ExerciseStatusResolver;
use App\Services\Workouts\Progression\TrainingHistory;
use App\Services\Workouts\Progression\WorkoutProgressionAnalyzer;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->user = User::factory()->create();

    foreach (['2026-01-01', '2026-01-08', '2026-01-15'] as $week => $date) {
        createExerciseStatusPlan($this->user, $date, [
            'benchPress' => [100, 'justRight'],
            'barbellBackSquat' => [100 + $week * 5, 'justRight'],
            'latPulldown' => [60, $week === 2 ? 'veryHard' : 'justRight'],
            'catCow' => [0, 'justRight'],
        ]);
    }
});

/**
 * @return array<string, ExerciseStatus>
 */
function resolveExerciseStatuses(User $user, bool $startsNewPhase): array
{
    $trainingHistory = new TrainingHistory($user);
    $analysis = new WorkoutProgressionAnalyzer($trainingHistory, app(ExerciseCatalog::class))->analyze();

    return collect(new ExerciseStatusResolver($trainingHistory, app(ExerciseCatalog::class))->resolve($analysis, $startsNewPhase))
        ->map(fn (array $exerciseStatus): ExerciseStatus => $exerciseStatus['status'])
        ->all();
}

test('gives each resistance exercise a single status', function () {
    expect(resolveExerciseStatuses($this->user, startsNewPhase: true))->toBe([
        'barbellBackSquat' => ExerciseStatus::Progress,
        'benchPress' => ExerciseStatus::Rotate,
        'latPulldown' => ExerciseStatus::Reduce,
    ]);
});

test('keeps stale plateaued exercises until a new phase starts', function () {
    expect(resolveExerciseStatuses($this->user, startsNewPhase: false))
        ->toHaveKey('benchPress', ExerciseStatus::Keep);
});

/**
 * @param  array<string, array{float|int, string}>  $results  exercise => [weight, feedback]
 */
function createExerciseStatusPlan(User $user, string $date, array $results): void
{
    $completedAt = Carbon::parse($date);
    $plan = WorkoutPlan::factory()->for($user)->create(['phase' => 'accumulation', 'created_at' => $completedAt]);
    $workoutDay = WorkoutDay::factory()->for($user)->for($plan, 'plan')->create(['status' => 'completed']);

    foreach ($results as $exercise => [$weight, $feedback]) {
        $plannedExercise = PlannedExercise::factory()->for($workoutDay, 'workoutDay')->create(['exercise' => $exercise]);
        ExerciseResult::factory()->for($plannedExercise)->create([
            'feedback' => $feedback,
            'completed_at' => $completedAt,
            'completed_sets' => 3,
            'completed_reps' => 8,
            'completed_weight' => $weight,
            'per_set_reps' => [8, 8, 8],
            'per_set_weights_kg' => array_fill(0, 3, $weight),
        ]);
    }
}
