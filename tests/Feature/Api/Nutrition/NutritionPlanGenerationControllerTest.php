<?php

use App\Ai\Agents\Nutrition\NutritionPlanGeneratorAgent;
use App\Enums\SubscriptionStatus;
use App\Models\NutritionProfile;
use App\Models\TrainingPreference;
use App\Models\User;
use Illuminate\Support\Carbon;

test('it persists and returns a generated nutrition plan using the sync payload', function () {
    $this->travelTo('2026-10-05 09:00:00');
    $user = nutritionPlanGenerationUser();
    NutritionPlanGeneratorAgent::fake([nutritionPlanGenerationResponse()])->preventStrayPrompts();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/nutrition-plans/generate', [], [
        'Idempotency-Key' => 'nutrition-plan-generation-1',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.id', fn (string $id): bool => $id !== '')
        ->assertJsonPath('data.starts_on', '2026-10-05T00:00:00.000000Z')
        ->assertJsonPath('data.goal', 'buildMuscle')
        ->assertJsonPath('data.days.0.target_calories', 2272)
        ->assertJsonPath('data.days.0.meals.0.title', 'Breakfast')
        ->assertJsonPath('data.days.0.meals.0.example_foods.0.amountGrams', 60);

    $this->assertDatabaseCount('nutrition_plans', 1);
    $this->assertDatabaseCount('nutrition_days', 7);
    $this->assertDatabaseCount('nutrition_meals', 7);
});

test('it returns 401 when no token is provided', function () {
    $this->postJson('/api/nutrition-plans/generate')
        ->assertUnauthorized();
});

test('it returns 403 when the user does not have app access', function () {
    $user = User::factory()->create();
    $user->subscription()->update(['status' => SubscriptionStatus::Expired]);

    $this->actingAs($user, 'sanctum')->postJson('/api/nutrition-plans/generate')
        ->assertForbidden()
        ->assertJsonPath('code', 'subscription_required');
});

function nutritionPlanGenerationUser(): User
{
    $user = User::factory()->create([
        'height_cm' => 180,
        'weight_kg' => 80,
        'birth_date' => Carbon::parse('1996-10-05'),
        'gender' => 'male',
        'language' => 'en',
    ]);
    $user->trainingPreferences()->save(TrainingPreference::factory()->make([
        'goal' => 'buildMuscle',
        'lifestyle' => 'sedentary',
        'body_composition_phase' => 'bulk',
        'workout_days' => ['monday'],
    ]));
    NutritionProfile::factory()->for($user)->create();

    return $user;
}

/** @return array<string, mixed> */
function nutritionPlanGenerationResponse(): array
{
    return [
        'startsOn' => '2026-10-05',
        'goal' => 'buildMuscle',
        'bodyCompositionPhase' => 'bulk',
        'dailyCalorieAverage' => 2606,
        'notes' => 'Keep meals consistent.',
        'days' => collect(range(0, 6))->map(fn (int $offset): array => [
            'date' => Carbon::parse('2026-10-05')->addDays($offset)->toDateString(),
            'weekday' => strtolower(Carbon::parse('2026-10-05')->addDays($offset)->englishDayOfWeek),
            'dayType' => 'rest',
            'targetMacros' => ['calories' => 1000, 'protein' => 100, 'carbs' => 100, 'fat' => 50],
            'energyDemand' => 'low',
            'notes' => 'Follow the plan.',
            'meals' => [[
                'title' => 'Breakfast',
                'orderIndex' => 0,
                'mealType' => 'breakfast',
                'targetMacros' => ['calories' => 500, 'protein' => 30, 'carbs' => 50, 'fat' => 15],
                'exampleFoods' => [['name' => 'Oats', 'amountGrams' => 60]],
            ]],
        ])->all(),
    ];
}
