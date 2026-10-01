<?php

namespace Database\Factories;

use App\Models\NutritionDay;
use App\Models\NutritionMeal;
use App\Models\NutritionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * @extends Factory<NutritionDay>
 */
class NutritionDayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nutrition_plan_id' => NutritionPlan::factory(),
            'date' => today(),
            'weekday' => strtolower(today()->englishDayOfWeek),
            'day_type' => 'rest',
            'target_calories' => 2300,
            'target_protein_grams' => 165,
            'target_carbs_grams' => 240,
            'target_fat_grams' => 75,
            'energy_demand' => 'low',
        ];
    }

    public function withMeals(int $mealCount = 4): static
    {
        return $this->afterCreating(function (NutritionDay $nutritionDay) use ($mealCount): void {
            NutritionMeal::factory()
                ->count($mealCount)
                ->sequence(fn (Sequence $sequence) => ['order_index' => $sequence->index])
                ->for($nutritionDay, 'day')
                ->create();
        });
    }
}
