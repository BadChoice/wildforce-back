<?php

use App\Enums\CoachingEnrollmentStatus;
use App\Enums\WorkoutKind;
use App\Models\CoachingEnrollment;
use App\Models\User;
use App\Models\WorkoutDay;
use Livewire\Livewire;

test('it lets a coach create and delete workout templates from the sidebar page', function () {
    $coach = User::factory()->create();
    $client = User::factory()->create();
    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);
    $this->actingAs($coach);

    $this->get(route('workout-templates.index'))
        ->assertOk()
        ->assertSee('Workout templates');

    Livewire::test('workout-templates.index')
        ->call('openWorkoutDayEditor')
        ->set('workoutDayTitle', 'Upper-body strength')
        ->set('workoutDayFocus', 'upperBody')
        ->call('addExercise', 'benchPress')
        ->call('saveWorkoutDay')
        ->assertSet('showWorkoutDayEditor', false)
        ->assertSee('Upper-body strength');

    $template = WorkoutDay::query()->where('title', 'Upper-body strength')->sole();

    expect($template->user_id)->toBe($coach->id)
        ->and($template->workout_plan_id)->toBeNull()
        ->and($template->kind)->toBe(WorkoutKind::Template)
        ->and($template->blocks()->sole()->exercises()->sole()->exercise)->toBe('benchPress');

    Livewire::test('workout-templates.index')
        ->call('deleteTemplate', $template->id)
        ->assertDontSee('Upper-body strength');

    $this->assertSoftDeleted('workout_days', ['id' => $template->id]);
});
