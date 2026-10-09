<?php

namespace Database\Factories;

use App\Models\CoachExerciseContent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoachExerciseContent>
 */
class CoachExerciseContentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'coach_user_id' => User::factory(),
            'exercise' => fake()->randomElement(['barbellBenchPress', 'barbellBackSquat', 'latPulldown', 'barbellRomanianDeadlift']),
            'image_path' => null,
            'youtube_video_id' => 'dQw4w9WgXcQ',
            'notes' => null,
        ];
    }

    public function withImage(): static
    {
        return $this->state(fn (array $attributes): array => [
            'image_path' => 'coach-exercise-images/'.fake()->uuid().'.jpg',
        ]);
    }
}
