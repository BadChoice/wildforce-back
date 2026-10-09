<?php

use App\Ai\Agents\Workouts\SingleWorkoutFromTextAgent;
use App\Enums\CoachingEnrollmentStatus;
use App\Enums\WorkoutKind;
use App\Models\AiUsage;
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
        ->set('exerciseCategory', 'strength')
        ->assertSee('Bench Press')
        ->assertDontSee('Push-Up')
        ->set('exerciseMuscle', 'glutes')
        ->assertDontSee('Bench Press')
        ->set('workoutDayTitle', 'Upper-body strength')
        ->set('workoutDayFocus', 'upperBody')
        ->set('exerciseCategory', '')
        ->set('exerciseMuscle', '')
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
        ->call('openExistingWorkoutDayEditor', $template->id)
        ->assertSet('workoutDayTitle', 'Upper-body strength')
        ->set('workoutDayTitle', 'Upper-body hypertrophy')
        ->set('workoutDayEstimatedDurationMinutes', '50')
        ->call('saveWorkoutDay')
        ->assertSet('showWorkoutDayEditor', false)
        ->assertSee('Upper-body hypertrophy');

    $template->refresh();

    expect($template->title)->toBe('Upper-body hypertrophy')
        ->and($template->estimated_duration_minutes)->toBe(50)
        ->and($template->blocks()->sole()->exercises()->sole()->exercise)->toBe('benchPress');

    Livewire::test('workout-templates.index')
        ->call('deleteTemplate', $template->id)
        ->assertDontSee('Upper-body strength');

    $this->assertSoftDeleted('workout_days', ['id' => $template->id]);
});

test('it fills a new template editor from free-form text', function () {
    $coach = User::factory()->create();
    $client = User::factory()->create();
    CoachingEnrollment::create(['client_user_id' => $client->id, 'coach_user_id' => $coach->id, 'status' => CoachingEnrollmentStatus::Active, 'starts_at' => now()]);
    SingleWorkoutFromTextAgent::fake([templateWorkoutFromTextResponse()])->preventStrayPrompts();
    $this->actingAs($coach);

    $component = Livewire::test('workout-templates.index')
        ->call('openTemplateFromText')
        ->set('workoutText', '1. Remo Tandem — 59 kg — 3 series')
        ->call('generateTemplateFromText')
        ->assertSet('showWorkoutDayEditor', true)
        ->assertSet('workoutDayTitle', 'Back template')
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

    $template = WorkoutDay::query()->where('title', 'Back template')->sole();

    expect($template->blocks()->count())->toBe(2);
});

test('it rejects a template focus that is not a WorkoutFocus case', function () {
    $coach = User::factory()->create();
    $client = User::factory()->create();
    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);
    $this->actingAs($coach);

    Livewire::test('workout-templates.index')
        ->call('openWorkoutDayEditor')
        ->set('workoutDayTitle', 'Invalid focus template')
        ->set('workoutDayFocus', 'back')
        ->call('saveWorkoutDay')
        ->assertHasErrors(['workoutDayFocus']);

    $this->assertDatabaseMissing('workout_days', ['title' => 'Invalid focus template']);
});

/** @return array<string, mixed> */
function templateWorkoutFromTextResponse(): array
{
    return [
        'title' => 'Back template', 'focus' => 'pull', 'dayType' => 'hypertrophy', 'estimatedDurationMinutes' => 55,
        'blocks' => [
            ['type' => 'standard', 'rounds' => 1, 'exercises' => [['exercise' => 'bentOverRow', 'sets' => 3, 'repsMin' => 8, 'repsMax' => 12, 'targetWeightKg' => 59, 'restSeconds' => 90]]],
            ['type' => 'standard', 'rounds' => 1, 'exercises' => [['exercise' => 'benchPress', 'sets' => 3, 'repsMin' => 8, 'repsMax' => 12, 'targetWeightKg' => 40, 'restSeconds' => 90]]],
        ],
    ];
}

test('it does not generate a template from text once the coach has spent the monthly AI budget', function () {
    $coach = User::factory()->create();
    $client = User::factory()->create();
    CoachingEnrollment::create(['client_user_id' => $client->id, 'coach_user_id' => $coach->id, 'status' => CoachingEnrollmentStatus::Active, 'starts_at' => now()]);
    AiUsage::factory()->for($coach)->create(['cost_micros' => $coach->monthlyAiBudgetInMicros()]);
    SingleWorkoutFromTextAgent::fake()->preventStrayPrompts();
    $this->actingAs($coach);

    Livewire::test('workout-templates.index')
        ->set('workoutText', '1. Remo Tandem — 59 kg — 3 series')
        ->call('generateTemplateFromText')
        ->assertHasErrors('workoutText')
        ->assertSet('showWorkoutDayEditor', false);

    SingleWorkoutFromTextAgent::assertNeverPrompted();
});
