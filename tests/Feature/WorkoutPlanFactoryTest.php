<?php

use App\Models\User;
use App\Models\WorkoutPlan;

test('it creates a complete workout plan for its user', function () {
    $user = User::factory()->create();

    $workoutPlan = WorkoutPlan::factory()
        ->for($user)
        ->complete(workoutDayCount: 2, blockCount: 2, exerciseCount: 3)
        ->create();

    $workoutPlan->load('workoutDays.blocks.exercises');

    expect($workoutPlan->user_id)->toBe($user->id)
        ->and($workoutPlan->workoutDays)->toHaveCount(2)
        ->and($workoutPlan->workoutDays->pluck('user_id')->unique()->all())->toBe([$user->id])
        ->and($workoutPlan->workoutDays->pluck('workout_plan_id')->unique()->all())->toBe([$workoutPlan->id]);

    foreach ($workoutPlan->workoutDays as $workoutDay) {
        expect($workoutDay->blocks)->toHaveCount(2);

        foreach ($workoutDay->blocks as $workoutBlock) {
            expect($workoutBlock->exercises)->toHaveCount(3)
                ->and($workoutBlock->exercises->pluck('workout_day_id')->unique()->all())->toBe([$workoutDay->id]);
        }
    }
});
