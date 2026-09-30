<?php

namespace Database\Factories;

use App\Models\NutritionProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NutritionProfile>
 */
class NutritionProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'dietary_style' => 'mediterranean',
            'meals_per_day_preference' => 4,
            'preferred_eating_window_start_hour' => 8,
            'preferred_eating_window_end_hour' => 21,
            'excluded_foods' => [],
            'allergies_and_intolerances' => [],
            'cooking_effort' => 'medium',
            'budget_sensitivity' => 'medium',
            'preferred_protein_sources' => ['chicken', 'eggs', 'greekYogurt', 'lentils'],
            'dislikes' => [],
            'wants_meal_suggestions' => true,
            'notes' => 'Prefereix receptes senzilles entre setmana.',
        ];
    }
}
