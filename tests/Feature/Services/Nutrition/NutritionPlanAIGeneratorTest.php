<?php

use App\Ai\Agents\Nutrition\NutritionPlanGeneratorAgent;
use App\Models\NutritionProfile;
use App\Models\TrainingPreference;
use App\Models\User;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use App\Services\Nutrition\NutritionPlanAIGenerator;
use Illuminate\Support\Carbon;

test('returns an unsaved nutrition plan while preserving deterministic daily targets', function () {
    $this->travelTo('2026-10-05 09:00:00');
    $user = nutritionPlanUser();
    $workoutPlan = WorkoutPlan::factory()->for($user)->create(['starts_on' => '2026-10-05']);
    WorkoutDay::factory()->for($user)->for($workoutPlan, 'plan')->create(['intended_weekday' => 'monday', 'focus' => 'fullBody']);
    NutritionPlanGeneratorAgent::fake([nutritionPlanResponse()])->preventStrayPrompts();

    $plan = app(NutritionPlanAIGenerator::class)->generate($user);

    expect($plan->exists)->toBeFalse()
        ->and($plan->user_id)->toBe($user->id)
        ->and($plan->source_workout_plan_id)->toBe($workoutPlan->id)
        ->and($plan->goal)->toBe('buildMuscle')
        ->and($plan->body_composition_phase)->toBe('bulk')
        ->and($plan->days)->toHaveCount(7)
        ->and($plan->days->first()->target_calories)->toBe('2606.00')
        ->and($plan->days->first()->target_protein_grams)->toBe('144.00')
        ->and($plan->days->first()->target_carbs_grams)->toBe('364.00')
        ->and($plan->days->first()->target_fat_grams)->toBe('64.00')
        ->and($plan->days->first()->planned_workout_title)->toBe('Full body')
        ->and($plan->days->first()->meals)->toHaveCount(1);

    $this->assertDatabaseCount('nutrition_plans', 0);
    $this->assertDatabaseCount('nutrition_days', 0);
    $this->assertDatabaseCount('nutrition_meals', 0);

    NutritionPlanGeneratorAgent::assertPrompted(fn ($prompt): bool => $prompt
        ->contains('## Client context')
        && $prompt->contains('Goal: buildMuscle')
        && $prompt->contains('## Deterministic daily targets')
        && $prompt->contains('calories: 2606'));
});

test('returns the nutrition prompt and response schema without prompting the AI', function () {
    $this->travelTo('2026-10-05 09:00:00');
    $user = nutritionPlanUser();
    NutritionPlanGeneratorAgent::fake()->preventStrayPrompts();

    $preview = app(NutritionPlanAIGenerator::class)->preview($user);
    $schema = json_decode($preview['schema'], true, flags: JSON_THROW_ON_ERROR);

    expect($preview['instructions'])->toContain('expert sports nutrition planner')
        ->and($preview['instructions'])->toContain('deterministic constraints')
        ->and($preview['prompt'])->toContain('## Nutrition preferences')
        ->and($preview['prompt'])->toContain('## Deterministic daily targets')
        ->and($preview['prompt'])->toContain('Wants meal suggestions: Yes')
        ->and($schema)->toHaveKey('properties.days')
        ->and($schema['properties']['days']['minItems'])->toBe(7)
        ->and($schema['properties']['days']['maxItems'])->toBe(7)
        ->and($schema['additionalProperties'])->toBeFalse();

    NutritionPlanGeneratorAgent::assertNeverPrompted();
});

function nutritionPlanUser(): User
{
    $user = User::factory()->create([
        'height' => 180,
        'weight' => 80,
        'birth_date' => Carbon::parse('1996-10-05'),
        'gender' => 'male',
        'language' => 'en',
    ]);
    $user->trainingPreferences()->save(TrainingPreference::factory()->make([
        'goal' => 'buildMuscle',
        'lifestyle' => 'sedentary',
        'gym_type' => 'commercialGym',
        'general_training_level' => 'intermediate',
        'training_split_preference' => 'automatic',
        'preferred_workout_duration_minutes' => 60,
        'workout_days' => ['monday'],
    ]));
    NutritionProfile::factory()->for($user)->create();

    return $user;
}

/** @return array<string, mixed> */
function nutritionPlanResponse(): array
{
    return [
        'startsOn' => '2026-10-05', 'goal' => 'buildMuscle', 'bodyCompositionPhase' => 'bulk', 'dailyCalorieAverage' => 2000, 'notes' => 'Keep meals consistent.',
        'days' => collect(range(0, 6))->map(fn (int $offset): array => [
            'date' => Carbon::parse('2026-10-05')->addDays($offset)->toDateString(),
            'weekday' => strtolower(Carbon::parse('2026-10-05')->addDays($offset)->englishDayOfWeek),
            'dayType' => 'rest', 'targetMacros' => ['calories' => 1000, 'protein' => 100, 'carbs' => 100, 'fat' => 50], 'energyDemand' => 'low', 'notes' => 'Follow the plan.',
            'meals' => [[
                'title' => 'Breakfast', 'orderIndex' => 0, 'mealType' => 'breakfast',
                'targetMacros' => ['calories' => 500, 'protein' => 30, 'carbs' => 50, 'fat' => 15], 'exampleFoods' => [['name' => 'Oats', 'amountGrams' => 60]],
            ]],
        ])->all(),
    ];
}
