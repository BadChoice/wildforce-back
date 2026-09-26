<?php

use App\Models\ExerciseProfile;
use App\Models\NutritionPlan;
use App\Models\TrainingPreference;
use App\Models\User;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use Illuminate\Support\Str;
use Livewire\Livewire;

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

test('dashboard displays the selected user training details', function () {
    $user = User::factory()->create([
        'name' => 'Alex Morgan',
        'email' => 'alex@example.com',
    ]);

    $trainingPreference = new TrainingPreference;
    $trainingPreference->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'goal' => 'buildStrength',
        'general_training_level' => 'intermediate',
        'workout_days' => ['monday', 'wednesday'],
    ])->save();

    $exerciseProfile = new ExerciseProfile;
    $exerciseProfile->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'exercise' => 'Back squat',
        'working_weight' => 100,
    ])->save();

    $workoutPlan = new WorkoutPlan;
    $workoutPlan->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'name' => 'Strength foundation',
        'status' => 'active',
    ])->save();

    $workoutDay = new WorkoutDay;
    $workoutDay->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'workout_plan_id' => $workoutPlan->id,
        'title' => 'Lower body',
        'focus' => 'lowerBody',
    ])->save();

    $this->actingAs($user);

    Livewire::test('dashboard.user-plan-list')
        ->call('selectUser', $user->id)
        ->assertSee('Alex Morgan')
        ->assertSee('Training profile')
        ->assertSee('Build Strength')
        ->assertSee('Strength foundation')
        ->assertSee('Lower body')
        ->call('selectTab', 'exercise-profiles')
        ->assertSee('Exercise profiles')
        ->assertSee('Back squat')
        ->assertSee('100.00 kg');
});
