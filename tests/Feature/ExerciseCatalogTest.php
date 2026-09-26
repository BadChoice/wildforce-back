<?php

use App\Models\User;

test('guests are redirected from the exercise catalog', function () {
    $this->get(route('exercises.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can explore and filter the exercise catalog', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('exercises.index', ['search' => 'walking', 'category' => 'cardio', 'muscle' => 'cardio']))
        ->assertOk()
        ->assertSee('Exercise catalog')
        ->assertSee('Walking')
        ->assertSee('walking')
        ->assertDontSee('Barbell Back Squat');
});
