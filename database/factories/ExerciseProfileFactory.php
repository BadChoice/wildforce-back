<?php

namespace Database\Factories;

use App\Models\ExerciseProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExerciseProfile>
 */
class ExerciseProfileFactory extends Factory
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
            'exercise' => fake()->randomElement(['barbellBenchPress', 'barbellBackSquat', 'latPulldown', 'romanianDeadlift']),
            'level' => 'intermediate',
            'working_weight' => fake()->randomFloat(2, 20, 100),
            'estimated_one_rep_max' => fake()->randomFloat(2, 30, 140),
            'max_reps' => 12,
            'preferred_rep_range_min' => 8,
            'preferred_rep_range_max' => 12,
            'notes' => null,
        ];
    }
}
