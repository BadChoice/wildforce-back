<?php

use App\Enums\CoachingEnrollmentStatus;
use App\Enums\WorkoutKind;
use App\Jobs\CopyTemplateWorkoutToWorkoutPlan;
use App\Models\CoachingEnrollment;
use App\Models\ExerciseResult;
use App\Models\PlannedExercise;
use App\Models\User;
use App\Models\WorkoutBlock;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use Illuminate\Support\Str;

test('it deep copies a template workout into a user workout plan', function () {
    $coach = User::factory()->create();
    $client = User::factory()->create();
    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);
    $templateWorkout = WorkoutDay::factory()->for($coach)->create([
        'kind' => WorkoutKind::Template,
        'title' => 'Lower-body strength',
        'notes' => 'Controlled tempo.',
        'intended_weekday' => 'monday',
        'estimated_duration_minutes' => 55,
    ]);
    $templateBlock = WorkoutBlock::factory()->for($templateWorkout, 'workoutDay')->create(['notes' => 'Main lift']);
    $templateExercise = PlannedExercise::factory()
        ->for($templateWorkout, 'workoutDay')
        ->for($templateBlock, 'block')
        ->create(['exercise' => 'barbellBackSquat', 'sets' => 5]);
    $templateExerciseResult = new ExerciseResult;
    $templateExerciseResult->forceFill([
        'id' => (string) Str::uuid(),
        'planned_exercise_id' => $templateExercise->id,
        'feedback' => 'justRight',
        'completed_at' => now(),
    ]);
    $templateExerciseResult->save();
    PlannedExercise::factory()->for($templateWorkout, 'workoutDay')->create(['exercise' => 'plank']);
    $workoutPlan = WorkoutPlan::factory()->for($client)->create();

    $job = new CopyTemplateWorkoutToWorkoutPlan($templateWorkout, $workoutPlan, '2026-10-06');
    $job->handle();

    $copiedWorkout = $workoutPlan->workoutDays()
        ->where('source_workout_day_id', $templateWorkout->id)
        ->with('blocks.exercises', 'directExercises')
        ->sole();

    expect($copiedWorkout->user_id)->toBe($client->id)
        ->and($copiedWorkout->kind)->toBe(WorkoutKind::Workout)
        ->and($copiedWorkout->status)->toBe('planned')
        ->and($copiedWorkout->title)->toBe('Lower-body strength')
        ->and($copiedWorkout->notes)->toBe('Controlled tempo.')
        ->and($copiedWorkout->intended_weekday)->toBe('monday')
        ->and($copiedWorkout->scheduled_for?->toDateString())->toBe('2026-10-06')
        ->and($copiedWorkout->estimated_duration_minutes)->toBe(55)
        ->and($copiedWorkout->blocks)->toHaveCount(1)
        ->and($copiedWorkout->blocks->sole()->notes)->toBe('Main lift')
        ->and($copiedWorkout->blocks->sole()->exercises)->toHaveCount(1)
        ->and($copiedWorkout->blocks->sole()->exercises->sole()->exercise)->toBe('barbellBackSquat')
        ->and($copiedWorkout->blocks->sole()->exercises->sole()->sets)->toBe(5)
        ->and($copiedWorkout->directExercises)->toHaveCount(1)
        ->and($copiedWorkout->directExercises->sole()->exercise)->toBe('plank');

    expect(ExerciseResult::query()
        ->whereBelongsTo($copiedWorkout->blocks->sole()->exercises->sole(), 'plannedExercise')
        ->count())->toBe(0);
});

test('it does not copy a template into a plan owned by a non-client', function () {
    $coach = User::factory()->create();
    $otherUser = User::factory()->create();
    $templateWorkout = WorkoutDay::factory()->for($coach)->create(['kind' => WorkoutKind::Template]);
    $workoutPlan = WorkoutPlan::factory()->for($otherUser)->create();

    (new CopyTemplateWorkoutToWorkoutPlan($templateWorkout, $workoutPlan))->handle();

    expect($workoutPlan->workoutDays()->count())->toBe(0);
});

test('it copies a user template into their own workout plan', function () {
    $user = User::factory()->create();
    $templateWorkout = WorkoutDay::factory()->for($user)->create(['kind' => WorkoutKind::Template]);
    $workoutPlan = WorkoutPlan::factory()->for($user)->create();

    (new CopyTemplateWorkoutToWorkoutPlan($templateWorkout, $workoutPlan, '2026-10-08'))->handle();

    $this->assertDatabaseHas('workout_days', [
        'workout_plan_id' => $workoutPlan->id,
        'source_workout_day_id' => $templateWorkout->id,
        'scheduled_for' => '2026-10-08 00:00:00',
    ]);
});
