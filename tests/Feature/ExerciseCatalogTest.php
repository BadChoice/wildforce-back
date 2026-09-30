<?php

use App\Models\ExerciseProfile;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

test('guests are redirected from the exercise catalog', function () {
    $this->get(route('exercises.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can explore the exercise catalog', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('exercises.index'))
        ->assertOk()
        ->assertSee('Exercise catalog')
        ->assertSee('Walking')
        ->assertSee('barbellBackSquat')
        ->assertSee('data-flux-icon')
        ->assertSee('dumbbells');
});

test('the exercise catalog shows the selected exercise in a detail panel', function () {
    Livewire::test('exercises.catalog')
        ->call('selectExercise', 'walking')
        ->assertSet('selectedExerciseId', 'walking')
        ->assertSet('showExerciseDetail', true)
        ->assertSee('A natural, low-impact way to improve cardiovascular health and burn calories.')
        ->assertSee('Details and metadata')
        ->assertSee('Instructions')
        ->assertSee('Exercise details')
        ->assertDontSee('Profile');
});

test('the exercise catalog shows the selected user exercise profile', function () {
    $user = User::factory()->create();
    $exerciseProfile = new ExerciseProfile;
    $exerciseProfile->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'exercise' => 'walking',
        'level' => 'intermediate',
        'typical_duration_minutes' => 45,
        'typical_distance_km' => 4.5,
    ])->save();

    Livewire::test('exercises.catalog', ['user' => $user])
        ->call('selectExercise', 'walking')
        ->assertSee('Profile')
        ->call('selectExerciseTab', 'profile')
        ->assertSee('Intermediate')
        ->assertSee('45 min')
        ->assertSee('4.500 km');
});

test('the exercise catalog shows an empty profile state for the selected user', function () {
    $user = User::factory()->create();

    Livewire::test('exercises.catalog', ['user' => $user])
        ->call('selectExercise', 'walking')
        ->call('selectExerciseTab', 'profile')
        ->assertSee('This user has not created a profile for this exercise yet.');
});

test('the exercise catalog filters exercises by category and muscle', function () {
    Livewire::test('exercises.catalog')
        ->set('category', 'cardio')
        ->set('muscle', 'cardio')
        ->assertSee('Walking')
        ->assertDontSee('Barbell Back Squat');
});

