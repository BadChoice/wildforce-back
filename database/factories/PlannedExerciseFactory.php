<?php

namespace Database\Factories;

use App\Models\PlannedExercise;
use App\Models\WorkoutDay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlannedExercise>
 */
class PlannedExerciseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workout_day_id' => WorkoutDay::factory(),
            'exercise' => fake()->randomElement(['benchPress', 'barbellBackSquat', 'latPulldown', 'romanianDeadlift', 'dumbbellShoulderPress']),
            'order_index' => 0,
            'sets' => 3,
            'reps_min' => 8,
            'reps_max' => 12,
            'target_reps' => [8, 8, 8],
            'target_weight_kg' => 40,
            'rest_seconds' => 90,
        ];
    }
}
