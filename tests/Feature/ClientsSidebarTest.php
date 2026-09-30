<?php

use App\Enums\CoachingEnrollmentStatus;
use App\Models\BodyMetricEntry;
use App\Models\CoachingEnrollment;
use App\Models\PlannedExercise;
use App\Models\User;
use App\Models\WorkoutBlock;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use Livewire\Livewire;

test('it displays the clients navigation item only when the authenticated user has clients', function () {
    $coach = User::factory()->create();
    $this->actingAs($coach);

    $this->get(route('clients.index'))
        ->assertDontSee(route('clients.index'));

    $client = User::factory()->create(['name' => 'Alex Client']);
    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);

    $this->get(route('clients.index'))
        ->assertSee('Clients')
        ->assertSee('Alex Client');
});

test('it displays a selected client details and workout plans', function () {
    $coach = User::factory()->create();
    $client = User::factory()->create([
        'name' => 'Alex Client',
        'height_cm' => 180,
        'weight_kg' => 80,
    ]);
    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);
    $workoutPlan = WorkoutPlan::factory()->for($client)->create(['name' => 'Strength foundation']);
    WorkoutDay::factory()->for($client)->for($workoutPlan, 'plan')->create(['title' => 'Lower body']);
    $this->actingAs($coach);

    Livewire::test('dashboard.clients')
        ->call('selectClient', $client->id)
        ->assertSet('showClientDetail', true)
        ->assertSee('Alex Client')
        ->assertSee('180 cm')
        ->assertSee('80.00 kg')
        ->call('selectTab', 'training')
        ->assertSee('Strength foundation')
        ->assertSee('Lower body')
        ->assertSee('Progression analysis')
        ->call('openProgressionAnalysis')
        ->assertSet('showProgressionAnalysis', true)
        ->assertSee('Mesocycle timeline')
        ->assertSee('What to change next')
        ->call('closeProgressionAnalysis')
        ->assertSet('showProgressionAnalysis', false)
        ->call('selectTab', 'nutrition')
        ->assertSee('Nutrition details will be available here soon.')
        ->call('selectTab', 'body-metrics')
        ->assertSee('No body metrics yet');
});

test('it displays body metric charts for a selected client', function () {
    $coach = User::factory()->create();
    $client = User::factory()->create();
    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);
    $bodyMetric = new BodyMetricEntry;
    $bodyMetric->forceFill([
        'user_id' => $client->id,
        'type' => 'weight',
        'value' => 72.5,
        'recorded_at' => '2026-09-20 10:00:00',
    ])->save();
    $this->actingAs($coach);

    Livewire::test('dashboard.clients')
        ->call('selectClient', $client->id)
        ->call('selectTab', 'body-metrics')
        ->assertSee('Weight')
        ->assertSee('Latest: 72.500');
});

test('it does not allow a coach to inspect a user who is not their client', function () {
    $coach = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($coach);

    Livewire::test('dashboard.clients')
        ->call('selectClient', $otherUser->id)
        ->assertSet('selectedClientId', null)
        ->assertSet('showClientDetail', false);
});

test('it creates a draft workout plan for a selected client', function () {
    $coach = User::factory()->create();
    $client = User::factory()->create();
    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);
    $this->actingAs($coach);

    Livewire::test('dashboard.clients')
        ->call('selectClient', $client->id)
        ->call('selectTab', 'training')
        ->call('openWorkoutPlanForm')
        ->set('workoutPlanName', 'Autumn strength')
        ->set('workoutPlanGoal', 'gainStrength')
        ->set('workoutPlanNotes', 'Build a strong base.')
        ->call('saveWorkoutPlan')
        ->assertSet('showWorkoutPlanForm', false)
        ->assertSee('Autumn strength');

    $this->assertDatabaseHas('workout_plans', [
        'user_id' => $client->id,
        'name' => 'Autumn strength',
        'goal' => 'gainStrength',
        'notes' => 'Build a strong base.',
        'status' => 'draft',
    ]);
});

test('it saves a complete workout day for a selected client plan', function () {
    $coach = User::factory()->create();
    $client = User::factory()->create();
    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);
    $workoutPlan = WorkoutPlan::factory()->for($client)->create();
    $this->actingAs($coach);

    Livewire::test('dashboard.clients')
        ->call('selectClient', $client->id)
        ->call('openWorkoutDayEditor', $workoutPlan->id)
        ->set('workoutDayTitle', 'Lower strength')
        ->set('workoutDayNotes', 'Keep the tempo controlled.')
        ->set('workoutDayFocus', 'lowerBody')
        ->call('addExercise', 'barbellBackSquat')
        ->set('workoutDayBlocks.0.exercises.0.sets', 4)
        ->set('workoutDayBlocks.0.exercises.0.reps_min', 5)
        ->set('workoutDayBlocks.0.exercises.0.reps_max', 5)
        ->set('workoutDayBlocks.0.exercises.0.target_weight_kg', '100')
        ->call('saveWorkoutDay')
        ->assertSet('showWorkoutDayEditor', false);

    $workoutDay = WorkoutDay::query()->where('title', 'Lower strength')->sole();
    $workoutBlock = WorkoutBlock::query()->whereBelongsTo($workoutDay, 'workoutDay')->sole();
    $plannedExercise = PlannedExercise::query()->whereBelongsTo($workoutDay, 'workoutDay')->sole();

    expect($workoutDay->user_id)->toBe($client->id)
        ->and($workoutDay->workout_plan_id)->toBe($workoutPlan->id)
        ->and($workoutDay->focus)->toBe('lowerBody')
        ->and($workoutBlock->order_index)->toBe(0)
        ->and($plannedExercise->workout_block_id)->toBe($workoutBlock->id)
        ->and($plannedExercise->exercise)->toBe('barbellBackSquat')
        ->and($plannedExercise->sets)->toBe(4)
        ->and($plannedExercise->target_weight_kg)->toBe('100.00');
});

test('it updates a workout day and removes its deleted blocks and exercises', function () {
    $coach = User::factory()->create();
    $client = User::factory()->create();
    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);
    $workoutPlan = WorkoutPlan::factory()->for($client)->create();
    $workoutDay = WorkoutDay::factory()->for($client)->for($workoutPlan, 'plan')->create([
        'title' => 'Original lower body',
        'notes' => 'Original notes',
    ]);
    $removedBlock = WorkoutBlock::factory()->for($workoutDay, 'workoutDay')->create(['order_index' => 0]);
    $removedExercise = PlannedExercise::factory()
        ->for($workoutDay, 'workoutDay')
        ->for($removedBlock, 'block')
        ->create();
    $retainedBlock = WorkoutBlock::factory()->for($workoutDay, 'workoutDay')->create(['order_index' => 1]);
    $retainedExercise = PlannedExercise::factory()
        ->for($workoutDay, 'workoutDay')
        ->for($retainedBlock, 'block')
        ->create(['exercise' => 'benchPress']);
    $this->actingAs($coach);

    Livewire::test('dashboard.clients')
        ->call('selectClient', $client->id)
        ->call('openExistingWorkoutDayEditor', $workoutPlan->id, $workoutDay->id)
        ->assertSet('workoutDayTitle', 'Original lower body')
        ->call('removeWorkoutBlock', $removedBlock->id)
        ->call('addWorkoutBlock')
        ->call('addExercise', 'barbellBackSquat')
        ->set('workoutDayTitle', 'Updated lower body')
        ->set('workoutDayNotes', 'Updated notes')
        ->set('workoutDayBlocks.0.notes', 'Retained block notes')
        ->set('workoutDayBlocks.0.exercises.0.sets', 5)
        ->set('workoutDayBlocks.1.exercises.0.reps_min', 6)
        ->set('workoutDayBlocks.1.exercises.0.reps_max', 8)
        ->call('saveWorkoutDay')
        ->assertSet('showWorkoutDayEditor', false);

    $workoutDay->refresh();
    $this->assertSame('Updated lower body', $workoutDay->title);
    $this->assertSame('Updated notes', $workoutDay->notes);
    $this->assertSoftDeleted('workout_blocks', ['id' => $removedBlock->id]);
    $this->assertSoftDeleted('planned_exercises', ['id' => $removedExercise->id]);

    $retainedBlock->refresh();
    $retainedExercise->refresh();
    expect($retainedBlock->notes)->toBe('Retained block notes')
        ->and($retainedExercise->sets)->toBe(5)
        ->and(WorkoutBlock::query()->whereBelongsTo($workoutDay, 'workoutDay')->count())->toBe(2)
        ->and(PlannedExercise::query()->whereBelongsTo($workoutDay, 'workoutDay')->count())->toBe(2);
});
