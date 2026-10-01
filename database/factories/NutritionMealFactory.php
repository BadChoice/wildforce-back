<?php

namespace Database\Factories;

use App\Models\NutritionDay;
use App\Models\NutritionMeal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NutritionMeal>
 */
class NutritionMealFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nutrition_day_id' => NutritionDay::factory(),
            'title' => fake()->randomElement(['Esmorzar', 'Dinar', 'Berenar', 'Sopar']),
            'order_index' => 0,
            'meal_type' => fake()->randomElement(['breakfast', 'lunch', 'snack', 'dinner']),
            'target_calories' => 600,
            'target_protein_grams' => 40,
            'target_carbs_grams' => 60,
            'target_fat_grams' => 20,
            'guidance' => 'Inclou una font de proteïna i vegetals.',
            'example_foods' => ['greekYogurt', 'oats', 'berries'],
        ];
    }
}
