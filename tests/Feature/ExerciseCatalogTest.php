<?php

use App\Models\User;
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
        ->assertSee('Dumbbells');
});

test('the exercise catalog shows the selected exercise in a detail panel', function () {
    Livewire::test('exercises.catalog')
        ->call('selectExercise', 'walking')
        ->assertSet('selectedExerciseId', 'walking')
        ->assertSet('showExerciseDetail', true)
        ->assertSee('A natural, low-impact way to improve cardiovascular health and burn calories.')
        ->assertSee('Instructions')
        ->assertSee('Exercise details');
});

test('the exercise catalog filters exercises by category and muscle', function () {
    Livewire::test('exercises.catalog')
        ->set('category', 'cardio')
        ->set('muscle', 'cardio')
        ->assertSee('Walking')
        ->assertDontSee('Barbell Back Squat');
});
