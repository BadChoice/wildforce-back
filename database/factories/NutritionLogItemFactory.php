<?php

namespace Database\Factories;

use App\Models\NutritionLogEntry;
use App\Models\NutritionLogItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NutritionLogItem>
 */
class NutritionLogItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nutrition_log_entry_id' => NutritionLogEntry::factory(),
            'name' => fake()->randomElement(['Pit de pollastre', 'Arròs integral', 'Iogurt grec', 'Plàtan', 'Amanida verda']),
            'quantity' => 1,
            'unit' => 'serving',
            'amount_grams' => fake()->randomFloat(1, 80, 250),
            'calories' => fake()->randomFloat(1, 80, 650),
            'protein_grams' => fake()->randomFloat(1, 5, 45),
            'carbs_grams' => fake()->randomFloat(1, 5, 80),
            'fat_grams' => fake()->randomFloat(1, 1, 30),
            'order_index' => 0,
        ];
    }
}
