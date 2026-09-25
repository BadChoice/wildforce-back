<?php

use App\Models\ExerciseResult;
use App\Models\NutritionDay;
use App\Models\NutritionMeal;
use App\Models\NutritionPlan;
use App\Models\PlannedExercise;
use App\Models\TrainingLocation;
use App\Models\User;
use App\Models\WorkoutBlock;
use App\Models\WorkoutDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

test('it returns changed records and soft-deleted tombstones for the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $location = makeTrainingLocation($user, 'Home gym', '2026-09-22T12:00:00Z');
    $deletedLocation = makeTrainingLocation($user, 'Closed gym', '2026-09-22T13:00:00Z');
    $deletedLocation->forceFill(['deleted_at' => Carbon::parse('2026-09-22T13:00:00Z')]);
    $deletedLocation->timestamps = false;
    $deletedLocation->save();
    $otherLocation = makeTrainingLocation($otherUser, 'Private gym', '2026-09-22T14:00:00Z');
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/sync/pull?resource=training-locations&updated_after=2026-09-22T11%3A00%3A00Z');

    $response->assertOk()
        ->assertJsonPath('meta.resource', 'training-locations')
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['id' => $location->id, 'deleted_at' => null])
        ->assertJsonFragment(['id' => $deletedLocation->id])
        ->assertJsonMissing(['id' => $otherLocation->id]);

    expect($response->json('data.1.deleted_at'))->not->toBeNull();
});

test('it returns 401 when no token is provided', function () {
    $response = $this->getJson('/api/sync/pull?resource=training-locations');

    $response->assertUnauthorized();
});

test('it returns every changed record without a pagination limit', function () {
    $user = User::factory()->create();

    foreach (range(1, 101) as $index) {
        makeTrainingLocation($user, "Gym {$index}", '2026-09-22T12:00:00Z');
    }

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/sync/pull?resource=training-locations');

    $response->assertOk()
        ->assertJsonCount(101, 'data');
});

test('it pulls a complete workout day when an exercise result changed', function () {
    $user = User::factory()->create();
    $workoutDay = makeWorkoutDay($user, '2026-09-24T12:00:00Z');
    $block = makeWorkoutBlock($workoutDay, '2026-09-24T12:00:00Z');
    $exercise = makePlannedExercise($workoutDay, $block, '2026-09-24T12:00:00Z');
    $result = makeExerciseResult($exercise, '2026-09-24T14:00:00Z');
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/sync/workout-days?updated_after=2026-09-24T13%3A00%3A00Z');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $workoutDay->id)
        ->assertJsonPath('data.0.blocks.0.id', $block->id)
        ->assertJsonPath('data.0.blocks.0.exercises.0.id', $exercise->id)
        ->assertJsonPath('data.0.blocks.0.exercises.0.exercise_results.0.id', $result->id);
});

test('it pulls a complete nutrition plan when a meal changed', function () {
    $user = User::factory()->create();
    $plan = makeNutritionPlan($user, '2026-09-25T12:00:00Z');
    $day = makeNutritionDay($plan, '2026-09-25T12:00:00Z');
    $meal = makeNutritionMeal($day, '2026-09-25T14:00:00Z');
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/sync/nutrition-plans?updated_after=2026-09-25T13%3A00%3A00Z');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $plan->id)
        ->assertJsonPath('data.0.days.0.id', $day->id)
        ->assertJsonPath('data.0.days.0.meals.0.id', $meal->id);
});

function makeTrainingLocation(User $user, string $name, string $updatedAt): TrainingLocation
{
    $location = new TrainingLocation;
    $location->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'name' => $name,
        'equipment' => ['dumbbells'],
        'created_at' => Carbon::parse($updatedAt),
        'updated_at' => Carbon::parse($updatedAt),
    ]);
    $location->timestamps = false;
    $location->save();

    return $location;
}

function makeWorkoutDay(User $user, string $updatedAt): WorkoutDay
{
    $workoutDay = new WorkoutDay;
    $workoutDay->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'title' => 'Upper body',
        'focus' => 'upperBody',
        'status' => 'planned',
        'did_count_toward_streak' => false,
        'order_index' => 0,
        'creation_source' => 'generated',
        'created_at' => Carbon::parse($updatedAt),
        'updated_at' => Carbon::parse($updatedAt),
    ]);
    $workoutDay->timestamps = false;
    $workoutDay->save();

    return $workoutDay;
}

function makeWorkoutBlock(WorkoutDay $workoutDay, string $updatedAt): WorkoutBlock
{
    $block = new WorkoutBlock;
    $block->forceFill([
        'id' => (string) Str::uuid(),
        'workout_day_id' => $workoutDay->id,
        'type' => 'standard',
        'order_index' => 0,
        'rounds' => 1,
        'created_at' => Carbon::parse($updatedAt),
        'updated_at' => Carbon::parse($updatedAt),
    ]);
    $block->timestamps = false;
    $block->save();

    return $block;
}

function makePlannedExercise(WorkoutDay $workoutDay, WorkoutBlock $block, string $updatedAt): PlannedExercise
{
    $exercise = new PlannedExercise;
    $exercise->forceFill([
        'id' => (string) Str::uuid(),
        'workout_day_id' => $workoutDay->id,
        'workout_block_id' => $block->id,
        'exercise' => 'benchPress',
        'order_index' => 0,
        'created_at' => Carbon::parse($updatedAt),
        'updated_at' => Carbon::parse($updatedAt),
    ]);
    $exercise->timestamps = false;
    $exercise->save();

    return $exercise;
}

function makeExerciseResult(PlannedExercise $exercise, string $updatedAt): ExerciseResult
{
    $result = new ExerciseResult;
    $result->forceFill([
        'id' => (string) Str::uuid(),
        'planned_exercise_id' => $exercise->id,
        'feedback' => 'justRight',
        'completed_at' => Carbon::parse($updatedAt),
        'created_at' => Carbon::parse($updatedAt),
        'updated_at' => Carbon::parse($updatedAt),
    ]);
    $result->timestamps = false;
    $result->save();

    return $result;
}

function makeNutritionPlan(User $user, string $updatedAt): NutritionPlan
{
    $plan = new NutritionPlan;
    $plan->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'starts_on' => Carbon::parse($updatedAt),
        'goal' => 'generalFitness',
        'daily_calorie_average' => 2200,
        'created_at' => Carbon::parse($updatedAt),
        'updated_at' => Carbon::parse($updatedAt),
    ]);
    $plan->timestamps = false;
    $plan->save();

    return $plan;
}

function makeNutritionDay(NutritionPlan $plan, string $updatedAt): NutritionDay
{
    $day = new NutritionDay;
    $day->forceFill([
        'id' => (string) Str::uuid(),
        'nutrition_plan_id' => $plan->id,
        'date' => Carbon::parse($updatedAt)->toDateString(),
        'weekday' => 'thursday',
        'day_type' => 'rest',
        'target_calories' => 2200,
        'target_protein_grams' => 150,
        'target_carbs_grams' => 200,
        'target_fat_grams' => 70,
        'energy_demand' => 'low',
        'created_at' => Carbon::parse($updatedAt),
        'updated_at' => Carbon::parse($updatedAt),
    ]);
    $day->timestamps = false;
    $day->save();

    return $day;
}

function makeNutritionMeal(NutritionDay $day, string $updatedAt): NutritionMeal
{
    $meal = new NutritionMeal;
    $meal->forceFill([
        'id' => (string) Str::uuid(),
        'nutrition_day_id' => $day->id,
        'title' => 'Breakfast',
        'order_index' => 0,
        'meal_type' => 'breakfast',
        'target_calories' => 550,
        'target_protein_grams' => 35,
        'target_carbs_grams' => 50,
        'target_fat_grams' => 20,
        'created_at' => Carbon::parse($updatedAt),
        'updated_at' => Carbon::parse($updatedAt),
    ]);
    $meal->timestamps = false;
    $meal->save();

    return $meal;
}
