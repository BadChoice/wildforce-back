<?php

use App\Enums\CoachingEnrollmentStatus;
use App\Enums\WorkoutKind;
use App\Models\CoachingEnrollment;
use App\Models\User;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use Livewire\Livewire;

test('it renders scheduled workout days in their calendar cells', function () {
    $user = User::factory()->create();
    $workoutPlan = WorkoutPlan::factory()->for($user)->create(['starts_on' => '2026-10-05']);
    WorkoutDay::factory()->for($user)->for($workoutPlan, 'plan')->create([
        'title' => 'Upper body',
        'scheduled_for' => '2026-10-08',
    ]);
    $this->actingAs($user);

    Livewire::test('workout-plans.show', ['workoutPlan' => $workoutPlan])
        ->assertSee('Week 1')
        ->assertSee('Upper body');
});

test('it creates a workout day on the selected calendar date', function () {
    $user = User::factory()->create();
    $workoutPlan = WorkoutPlan::factory()->for($user)->create(['starts_on' => '2026-10-05']);
    $this->actingAs($user);

    Livewire::test('workout-plans.show', ['workoutPlan' => $workoutPlan])
        ->call('createWorkoutDay', '2026-10-08')
        ->assertSet('showWorkoutDayEditor', true)
        ->set('workoutDayTitle', 'Workout day')
        ->call('saveWorkoutDay');

    $this->assertDatabaseHas('workout_days', [
        'user_id' => $user->id,
        'workout_plan_id' => $workoutPlan->id,
        'kind' => WorkoutKind::Workout->value,
        'title' => 'Workout day',
        'scheduled_for' => '2026-10-08 00:00:00',
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
    $workoutPlan = WorkoutPlan::factory()->for($client)->create(['starts_on' => '2026-10-05']);
    $templateWorkout = WorkoutDay::factory()->for($coach)->create([
        'kind' => WorkoutKind::Template,
        'title' => 'Lower body',
    ]);
    $this->actingAs($client);

    Livewire::test('workout-plans.show', ['workoutPlan' => $workoutPlan])
        ->call('openTemplatePicker', '2026-10-08')
        ->assertSee('Add workout from template')
        ->call('copyTemplateWorkout', $templateWorkout->id);

    $this->assertDatabaseHas('workout_days', [
        'workout_plan_id' => $workoutPlan->id,
        'source_workout_day_id' => $templateWorkout->id,
        'scheduled_for' => '2026-10-08 00:00:00',
    ]);
});
