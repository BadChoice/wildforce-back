<?php

use App\Models\ExerciseProfile;
use App\Models\ExerciseResult;
use App\Models\PlannedExercise;
use App\Models\User;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use App\Services\ExerciseCatalog\ExerciseCatalog;
use App\Services\Workouts\Progression\MesocyclePhaseRules;
use App\Services\Workouts\Progression\TrainingHistory;
use App\Services\Workouts\Progression\WorkoutProgressionAnalyzer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

test('returns the progression state from a user training history', function () {
    $user = User::factory()->create(['current_streak' => 5]);
    createProgressionExerciseProfile($user, 'benchPress');

    createCompletedPlan($user, 1, '2026-01-01', 100, 'justRight');
    createCompletedPlan($user, 2, '2026-02-01', 105, 'justRight');
    createCompletedPlan($user, 3, '2026-03-01', 110, 'justRight');

    $analysis = new WorkoutProgressionAnalyzer(
        new TrainingHistory($user),
        app(ExerciseCatalog::class),
    )->analyze();

    expect($analysis->mesocycleNumber)->toBe(4)
        ->and($analysis->exerciseTrends)->toBe(['benchPress' => 'improving'])
        ->and($analysis->overallVolumeTrend)->toBe('increasing')
        ->and($analysis->staleExercises)->toBe(['benchPress'])
        ->and($analysis->completionRate)->toBe(1.0)
        ->and($analysis->recentCompletedWorkouts)->toBe(3)
        ->and($analysis->readinessLevel)->toBe('high')
        ->and($analysis->recentFeedbackBreakdown)->toBe(['justRight' => 3]);
});

test('returns low readiness when recent exercise feedback is predominantly hard', function () {
    $user = User::factory()->create(['current_streak' => 10]);
    createProgressionExerciseProfile($user, 'benchPress');

    createCompletedPlan($user, 1, '2026-01-01', 100, 'hard');
    createCompletedPlan($user, 2, '2026-02-01', 100, 'veryHard');
    createCompletedPlan($user, 3, '2026-03-01', 100, 'justRight');

    $analysis = new WorkoutProgressionAnalyzer(
        new TrainingHistory($user),
        app(ExerciseCatalog::class),
    )->analyze();

    expect($analysis->readinessLevel)->toBe('low')
        ->and($analysis->overallVolumeTrend)->toBe('stable')
        ->and($analysis->recentFeedbackBreakdown)->toBe([
            'hard' => 1,
            'veryHard' => 1,
            'justRight' => 1,
        ]);
});

test('uses complete non-deload exercise history while limiting plan-level analysis to recent plans', function () {
    $user = User::factory()->create(['current_streak' => 5]);
    createProgressionExerciseProfile($user, 'benchPress');

    createCompletedPlan($user, 1, '2026-01-01', 50, 'justRight');
    createCompletedPlan($user, 2, '2026-02-01', 100, 'justRight');
    createCompletedPlan($user, 3, '2026-03-01', 100, 'justRight');
    createCompletedPlan($user, 4, '2026-04-01', 100, 'justRight');

    $analysis = new WorkoutProgressionAnalyzer(
        new TrainingHistory($user),
        app(ExerciseCatalog::class),
    )->analyze();

    expect($analysis->mesocycleNumber)->toBe(5)
        ->and($analysis->exerciseTrends)->toBe(['benchPress' => 'improving'])
        ->and($analysis->overallVolumeTrend)->toBe('stable')
        ->and($analysis->recentCompletedWorkouts)->toBe(3)
        ->and($analysis->recentFeedbackBreakdown)->toBe(['justRight' => 3]);
});

test('resolves mesocycle phases by training level and cycle position', function () {
    expect(MesocyclePhaseRules::phaseInfo(4, 'beginner'))->toBe([
        'phase' => 'deload',
        'weekInPhase' => 1,
        'cycleLength' => 4,
        'positionInCycle' => 4,
    ])
        ->and(MesocyclePhaseRules::phaseInfo(4, 'intermediate'))->toBe([
            'phase' => 'intensification',
            'weekInPhase' => 1,
            'cycleLength' => 6,
            'positionInCycle' => 4,
        ])
        ->and(MesocyclePhaseRules::phaseInfo(6, 'intermediate'))->toBe([
            'phase' => 'deload',
            'weekInPhase' => 1,
            'cycleLength' => 6,
            'positionInCycle' => 6,
        ])
        ->and(MesocyclePhaseRules::phaseInfo(1, 'completeBeginner'))->toBeNull();
});

function createProgressionExerciseProfile(User $user, string $exercise): void
{
    ExerciseProfile::query()->forceCreate([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'exercise' => $exercise,
    ]);
}

function createCompletedPlan(User $user, int $mesocycleNumber, string $date, float $weight, string $feedback): void
{
    $createdAt = Carbon::parse($date);
    $plan = WorkoutPlan::factory()
        ->for($user)
        ->create([
            'mesocycle_number' => $mesocycleNumber,
            'phase' => 'accumulation',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    $workoutDay = WorkoutDay::factory()
        ->for($user)
        ->for($plan, 'plan')
        ->create([
            'status' => 'completed',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    $plannedExercise = PlannedExercise::factory()
        ->for($workoutDay, 'workoutDay')
        ->create([
            'exercise' => 'benchPress',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

    ExerciseResult::query()->forceCreate([
        'id' => (string) Str::uuid(),
        'planned_exercise_id' => $plannedExercise->id,
        'feedback' => $feedback,
        'completed_at' => $createdAt,
        'completed_sets' => 1,
        'completed_reps' => 10,
        'completed_weight' => $weight,
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}
