<?php

namespace Database\Factories;

use App\Models\ExerciseResult;
use App\Models\PlannedExercise;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExerciseResult>
 */
class ExerciseResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'planned_exercise_id' => PlannedExercise::factory(),
            'feedback' => fake()->randomElement(['justRight', 'tooEasy', 'tooHard']),
            'completed_at' => fake()->dateTimeBetween('-2 months', 'now'),
            'completed_sets' => 3,
            'completed_reps' => 30,
            'completed_weight' => fake()->randomFloat(2, 20, 100),
            'per_set_reps' => [10, 10, 10],
            'per_set_weights_kg' => [40, 40, 40],
        ];
    }
}
