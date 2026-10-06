<?php

use App\Ai\Agents\Workouts\SingleWorkoutFromTextAgent;
use App\Enums\CoachingEnrollmentStatus;
use App\Enums\WorkoutDayStatus;
use App\Enums\WorkoutKind;
use App\Models\CoachingEnrollment;
use App\Models\ExerciseResult;
use App\Models\PlannedExercise;
use App\Models\User;
use App\Models\WorkoutBlock;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use Livewire\Livewire;

test('it renders scheduled workout days in their calendar cells', function () {
    $user = User::factory()->create();
    $workoutPlan = WorkoutPlan::factory()->for($user)->create(['starts_on' => now()->startOfWeek()]);
    WorkoutDay::factory()->for($user)->for($workoutPlan, 'plan')->create([
        'title' => 'Upper body',
        'scheduled_for' => now()->startOfWeek()->addDays(3),
    ]);
    $this->actingAs($user);

    Livewire::test('workout-plans.show', ['workoutPlan' => $workoutPlan])
        ->assertSee('Week 1')
        ->assertSee('Upper body');
});

test('it renders workout days with their blocks and exercises in kanban view', function () {
    $user = User::factory()->create();
    $workoutPlan = WorkoutPlan::factory()->for($user)->create(['starts_on' => now()->startOfWeek()]);
    $workoutDay = WorkoutDay::factory()->for($user)->for($workoutPlan, 'plan')->create([
        'title' => 'Push day',
        'scheduled_for' => now()->startOfWeek()->addDays(2),
    ]);
    $block = WorkoutBlock::factory()->for($workoutDay)->create(['type' => 'superset']);
    PlannedExercise::factory()->for($workoutDay)->for($block, 'block')->create([
        'exercise' => 'benchPress',
        'sets' => 4,
        'reps_min' => 6,
        'reps_max' => 8,
    ]);
    $this->actingAs($user);

    Livewire::withQueryParams(['view' => 'kanban'])
        ->test('workout-plans.show', ['workoutPlan' => $workoutPlan])
        ->assertSet('viewMode', 'kanban')
        ->assertSee('Push day')
        ->assertSee('Superset')
        ->assertSee('Bench Press')
        ->assertSee('4 × 6–8')
        ->call('openWorkoutDayEditor', $workoutDay->id)
        ->assertSet('showWorkoutDayEditor', true);
});

test('it displays status badge for non-default workout day states', function () {
    $user = User::factory()->create();
    $workoutPlan = WorkoutPlan::factory()->for($user)->create(['starts_on' => now()->startOfWeek()]);

    WorkoutDay::factory()->for($user)->for($workoutPlan, 'plan')->create([
        'title' => 'Completed Leg Day',
        'status' => WorkoutDayStatus::Completed->value,
        'scheduled_for' => now()->startOfWeek()->addDays(1),
    ]);

    WorkoutDay::factory()->for($user)->for($workoutPlan, 'plan')->create([
        'title' => 'In Progress Chest Day',
        'status' => WorkoutDayStatus::InProgress->value,
        'scheduled_for' => now()->startOfWeek()->addDays(2),
    ]);

    WorkoutDay::factory()->for($user)->for($workoutPlan, 'plan')->create([
        'title' => 'Skipped Arm Day',
        'status' => WorkoutDayStatus::Skipped->value,
        'scheduled_for' => now()->startOfWeek()->addDays(3),
    ]);

    $this->actingAs($user);

    Livewire::test('workout-plans.show', ['workoutPlan' => $workoutPlan])
        ->assertSee('Completed')
        ->assertSee('In Progress')
        ->assertSee('Skipped');
});

test('it opens completed workout day view popup when pressing a completed workout day', function () {
    $user = User::factory()->create();
    $workoutPlan = WorkoutPlan::factory()->for($user)->create(['starts_on' => now()->startOfWeek()]);

    $workoutDay = WorkoutDay::factory()->for($user)->for($workoutPlan, 'plan')->create([
        'title' => 'Completed Powerlifting',
        'status' => WorkoutDayStatus::Completed->value,
        'focus' => 'gainStrength',
        'estimated_duration_minutes' => 60,
        'active_calories_burned' => 450,
        'total_volume_kg' => 2500,
        'scheduled_for' => now()->startOfWeek()->addDays(1),
        'completed_at' => now(),
    ]);

    $block = WorkoutBlock::factory()->for($workoutDay)->create([
        'type' => 'standard',
    ]);

    $plannedExercise = PlannedExercise::factory()->for($workoutDay)->for($block, 'block')->create([
        'exercise' => 'benchPress',
        'sets' => 3,
        'reps_min' => 8,
        'reps_max' => 10,
        'target_weight_kg' => 80,
        'skipped_at' => now(),
    ]);

    ExerciseResult::factory()->for($plannedExercise)->create([
        'completed_sets' => 3,
        'completed_weight' => 82.5,
        'per_set_reps' => [10, 9, 8],
        'per_set_weights_kg' => [82.5, 82.5, 82.5],
        'feedback' => 'justRight',
    ]);

    $this->actingAs($user);

    Livewire::test('workout-plans.show', ['workoutPlan' => $workoutPlan])
        ->call('openWorkoutDay', $workoutDay->id)
        ->assertSet('showWorkoutDayCompleted', true)
        ->assertSet('showWorkoutDayEditor', false)
        ->assertSee('Completed Powerlifting')
        ->assertSee('450 kcal')
        ->assertSee('2,500.0 kg')
        ->assertSee('Exercise Results')
        ->assertSee('Logged Results')
        ->assertSee('82.5 kg')
        ->assertSee('10 reps')
        ->assertSee('Skipped');
});

test('it opens editor when pressing a planned workout day', function () {
    $user = User::factory()->create();
    $workoutPlan = WorkoutPlan::factory()->for($user)->create(['starts_on' => now()->startOfWeek()]);

    $workoutDay = WorkoutDay::factory()->for($user)->for($workoutPlan, 'plan')->create([
        'title' => 'Planned Cardio',
        'status' => WorkoutDayStatus::Planned->value,
        'scheduled_for' => now()->startOfWeek()->addDays(1),
    ]);

    $this->actingAs($user);

    Livewire::test('workout-plans.show', ['workoutPlan' => $workoutPlan])
        ->call('openWorkoutDay', $workoutDay->id)
        ->assertSet('showWorkoutDayCompleted', false)
        ->assertSet('showWorkoutDayEditor', true)
        ->assertSet('workoutDayTitle', 'Planned Cardio');
});

test('it renders unscheduled workout days using intended weekday', function () {
    $user = User::factory()->create();
    $workoutPlan = WorkoutPlan::factory()->for($user)->create(['starts_on' => now()->startOfWeek()]);
    WorkoutDay::factory()->for($user)->for($workoutPlan, 'plan')->create([
        'title' => 'Leg day',
        'scheduled_for' => null,
        'intended_weekday' => 'wednesday',
    ]);
    $this->actingAs($user);

    Livewire::test('workout-plans.show', ['workoutPlan' => $workoutPlan])
        ->assertSee('Week 1')
        ->assertSee('Leg day');
});

test('it reschedules a planned workout day dropped on a visible date', function () {
    $user = User::factory()->create();
    $workoutPlan = WorkoutPlan::factory()->for($user)->create(['starts_on' => now()->startOfWeek()]);
    $workoutDay = WorkoutDay::factory()->for($user)->for($workoutPlan, 'plan')->create([
        'scheduled_for' => now()->startOfWeek()->addDay(),
    ]);
    $targetDate = now()->startOfWeek()->addDays(4)->toDateString();
    $this->actingAs($user);

    Livewire::test('workout-plans.show', ['workoutPlan' => $workoutPlan])
        ->call('rescheduleWorkoutDay', $workoutDay->id, $targetDate);

    $this->assertDatabaseHas('workout_days', [
        'id' => $workoutDay->id,
        'scheduled_for' => $targetDate.' 00:00:00',
        'intended_weekday' => 'friday',
    ]);
});

test('it does not reschedule a workout day that is not planned', function () {
    $user = User::factory()->create();
    $originalDate = now()->startOfWeek()->addDay();
    $workoutPlan = WorkoutPlan::factory()->for($user)->create(['starts_on' => now()->startOfWeek()]);
    $workoutDay = WorkoutDay::factory()->for($user)->for($workoutPlan, 'plan')->create([
        'status' => WorkoutDayStatus::Completed->value,
        'scheduled_for' => $originalDate,
    ]);
    $this->actingAs($user);

    Livewire::test('workout-plans.show', ['workoutPlan' => $workoutPlan])
        ->call('rescheduleWorkoutDay', $workoutDay->id, now()->startOfWeek()->addDays(4)->toDateString());

    $this->assertDatabaseHas('workout_days', [
        'id' => $workoutDay->id,
        'scheduled_for' => $originalDate->toDateTimeString(),
    ]);
});

test('it does not reschedule a workout day from another plan', function () {
    $user = User::factory()->create();
    $workoutPlan = WorkoutPlan::factory()->for($user)->create(['starts_on' => now()->startOfWeek()]);
    $otherWorkoutPlan = WorkoutPlan::factory()->for($user)->create(['starts_on' => now()->startOfWeek()]);
    $originalDate = now()->startOfWeek()->addDay();
    $otherWorkoutDay = WorkoutDay::factory()->for($user)->for($otherWorkoutPlan, 'plan')->create([
        'scheduled_for' => $originalDate,
    ]);
    $this->actingAs($user);

    Livewire::test('workout-plans.show', ['workoutPlan' => $workoutPlan])
        ->call('rescheduleWorkoutDay', $otherWorkoutDay->id, now()->startOfWeek()->addDays(4)->toDateString());

    $this->assertDatabaseHas('workout_days', [
        'id' => $otherWorkoutDay->id,
        'scheduled_for' => $originalDate->toDateTimeString(),
    ]);
});

test('it displays and updates workout plan details', function () {
    $coach = User::factory()->create();
    $client = User::factory()->create();
    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);
    $workoutPlan = WorkoutPlan::factory()->for($client)->create([
        'goal' => 'buildMuscle',
        'status' => 'draft',
        'mesocycle_number' => 2,
        'phase' => 'accumulation',
        'phase_week' => 2,
        'cycle_length' => 6,
        'body_composition_phase' => 'bulk',
    ]);
    $this->actingAs($coach);

    Livewire::test('workout-plans.show', ['workoutPlan' => $workoutPlan])
        ->assertSee('Draft')
        ->assertSee('Build Muscle')
        ->assertSee('Bulk')
        ->assertSee('Mesocycle')
        ->assertSee('Accumulation')
        ->call('openWorkoutPlanEditor')
        ->assertSet('showWorkoutPlanEditor', true)
        ->set('workoutPlanGoal', 'gainStrength')
        ->set('workoutPlanStatus', 'active')
        ->set('workoutPlanBodyCompositionPhase', 'maintain')
        ->set('workoutPlanMesocycleNumber', '3')
        ->set('workoutPlanPhase', 'intensification')
        ->set('workoutPlanPhaseWeek', '1')
        ->set('workoutPlanCycleLength', '8')
        ->call('saveWorkoutPlan')
        ->assertSet('showWorkoutPlanEditor', false)
        ->assertSee('Active')
        ->assertSee('Gain Strength')
        ->assertSee('Maintain')
        ->assertSee('Intensification');

    $workoutPlan->refresh();

    expect($workoutPlan->status)->toBe('active')
        ->and($workoutPlan->goal)->toBe('gainStrength')
        ->and($workoutPlan->body_composition_phase)->toBe('maintain')
        ->and($workoutPlan->mesocycle_number)->toBe(3)
        ->and($workoutPlan->phase)->toBe('intensification')
        ->and($workoutPlan->phase_week)->toBe(1)
        ->and($workoutPlan->cycle_length)->toBe(8);
});

test('it creates a workout day on the selected calendar date', function () {
    $user = User::factory()->create();
    $currentWeekStart = now()->startOfWeek()->toDateString();
    $targetDate = now()->startOfWeek()->addDays(3)->toDateString();
    $workoutPlan = WorkoutPlan::factory()->for($user)->create(['starts_on' => $currentWeekStart]);
    $this->actingAs($user);

    Livewire::test('workout-plans.show', ['workoutPlan' => $workoutPlan])
        ->call('createWorkoutDay', $targetDate)
        ->assertSet('showWorkoutDayEditor', true)
        ->set('workoutDayTitle', 'Workout day')
        ->call('saveWorkoutDay');

    $this->assertDatabaseHas('workout_days', [
        'user_id' => $user->id,
        'workout_plan_id' => $workoutPlan->id,
        'kind' => WorkoutKind::Workout->value,
        'title' => 'Workout day',
        'scheduled_for' => $targetDate.' 00:00:00',
    ]);
});

test('it copies a template into the selected calendar date', function () {
    $coach = User::factory()->create();
    $client = User::factory()->create();
    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);
    $currentWeekStart = now()->startOfWeek()->toDateString();
    $targetDate = now()->startOfWeek()->addDays(3)->toDateString();
    $workoutPlan = WorkoutPlan::factory()->for($client)->create(['starts_on' => $currentWeekStart]);
    $templateWorkout = WorkoutDay::factory()->for($coach)->create([
        'kind' => WorkoutKind::Template,
        'title' => 'Lower body',
    ]);
    $this->actingAs($client);

    Livewire::test('workout-plans.show', ['workoutPlan' => $workoutPlan])
        ->call('openTemplatePicker', $targetDate)
        ->assertSee('Add workout from template')
        ->call('copyTemplateWorkout', $templateWorkout->id);

    $this->assertDatabaseHas('workout_days', [
        'workout_plan_id' => $workoutPlan->id,
        'source_workout_day_id' => $templateWorkout->id,
        'scheduled_for' => $targetDate.' 00:00:00',
    ]);
});

test('it fills a new scheduled workout editor from free-form text', function () {
    $user = User::factory()->create();
    $targetDate = now()->startOfWeek()->addDays(3)->toDateString();
    $workoutPlan = WorkoutPlan::factory()->for($user)->create(['starts_on' => now()->startOfWeek()]);
    SingleWorkoutFromTextAgent::fake([workoutFromTextResponse()])->preventStrayPrompts();
    $this->actingAs($user);

    $component = Livewire::test('workout-plans.show', ['workoutPlan' => $workoutPlan])
        ->call('openWorkoutFromText', $targetDate)
        ->assertSet('showWorkoutFromText', true)
        ->set('workoutText', '1. Remo Tandem — 59 kg — 3 series')
        ->call('generateWorkoutFromText')
        ->assertSet('showWorkoutFromText', false)
        ->assertSet('showWorkoutDayEditor', true)
        ->assertSet('workoutDayTitle', 'Back day')
        ->assertSet('workoutDayBlocks.0.exercises.0.target_weight_kg', '59.00')
        ->assertSee('Block 2');

    $workoutDayBlocks = $component->get('workoutDayBlocks');

    expect($workoutDayBlocks)->toHaveCount(2)
        ->and($workoutDayBlocks[0]['id'])->toBeString()->not->toBeEmpty()
        ->and($workoutDayBlocks[1]['id'])->toBeString()->not->toBeEmpty()
        ->and($workoutDayBlocks[0]['id'])->not->toBe($workoutDayBlocks[1]['id'])
        ->and($workoutDayBlocks[1]['exercises'][0])->toBeArray();

    $component
        ->call('selectWorkoutBlock', $workoutDayBlocks[1]['id'])
        ->assertSet('selectedWorkoutBlockId', $workoutDayBlocks[1]['id'])
        ->call('saveWorkoutDay')
        ->assertSet('showWorkoutDayEditor', false);

    $workoutDay = WorkoutDay::query()->where('title', 'Back day')->sole();
    $workoutBlocks = WorkoutBlock::query()->whereBelongsTo($workoutDay, 'workoutDay')->orderBy('order_index')->get();
    $plannedExercise = PlannedExercise::query()->whereBelongsTo($workoutBlocks->first(), 'block')->sole();

    expect($workoutDay->workout_plan_id)->toBe($workoutPlan->id)
        ->and($workoutDay->scheduled_for?->toDateString())->toBe($targetDate)
        ->and($workoutBlocks)->toHaveCount(2)
        ->and($plannedExercise->exercise)->toBe('bentOverRow')
        ->and($plannedExercise->target_weight_kg)->toBe('59.00');
});

/** @return array<string, mixed> */
function workoutFromTextResponse(): array
{
    return [
        'title' => 'Back day', 'focus' => 'back', 'dayType' => 'hypertrophy', 'estimatedDurationMinutes' => 55,
        'blocks' => [
            ['type' => 'standard', 'rounds' => 1, 'exercises' => [['exercise' => 'bentOverRow', 'sets' => 3, 'repsMin' => 8, 'repsMax' => 12, 'targetWeightKg' => 59, 'restSeconds' => 90]]],
            ['type' => 'standard', 'rounds' => 1, 'exercises' => [['exercise' => 'benchPress', 'sets' => 3, 'repsMin' => 8, 'repsMax' => 12, 'targetWeightKg' => 40, 'restSeconds' => 90]]],
        ],
    ];
}
