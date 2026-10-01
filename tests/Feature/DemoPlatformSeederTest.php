<?php

use App\Models\CoachingEnrollment;
use App\Models\ExerciseResult;
use App\Models\NutritionLogEntry;
use App\Models\NutritionPlan;
use App\Models\User;
use App\Models\WorkoutDay;
use Database\Seeders\DatabaseSeeder;

test('creates a populated platform environment for development', function () {
    $this->seed(DatabaseSeeder::class);

    $this->assertDatabaseCount('users', 4);
    $this->assertDatabaseCount('coaching_enrollments', 2);

    $activeClient = User::query()->where('email', 'marta@wildforce.test')->sole();
    $newClient = User::query()->where('email', 'pau@wildforce.test')->sole();

    expect(CoachingEnrollment::query()->where('coach_user_id', User::query()->where('email', 'coach@wildforce.test')->value('id'))->count())->toBe(2)
        ->and(NutritionPlan::query()->whereBelongsTo($activeClient)->count())->toBe(1)
        ->and(NutritionLogEntry::query()->whereBelongsTo($activeClient)->count())->toBe(6)
        ->and(WorkoutDay::query()->whereBelongsTo($activeClient)->where('status', 'completed')->count())->toBe(1)
        ->and(ExerciseResult::query()->count())->toBeGreaterThan(0)
        ->and(WorkoutDay::query()->whereBelongsTo($newClient)->where('status', 'completed')->count())->toBe(0);
});
