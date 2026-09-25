<?php

use App\Models\NutritionPlan;
use App\Models\User;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use Illuminate\Support\Str;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard shows each user plan counts', function () {
    $user = User::factory()->create([
        'name' => 'Alex Morgan',
        'email' => 'alex@example.com',
    ]);
    $workoutPlan = new WorkoutPlan;
    $workoutPlan->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'name' => 'Strength plan',
    ])->save();

    $customWorkout = new WorkoutDay;
    $customWorkout->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'title' => 'Custom workout',
    ])->save();

    $plannedWorkout = new WorkoutDay;
    $plannedWorkout->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'workout_plan_id' => $workoutPlan->id,
        'title' => 'Planned workout',
    ])->save();

    $nutritionPlan = new NutritionPlan;
    $nutritionPlan->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'starts_on' => now(),
    ])->save();

    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertSee('Users and plans')
        ->assertSeeInOrder([
            'Alex Morgan',
            'alex@example.com',
            '1',
            '1',
            '1',
        ]);
});
