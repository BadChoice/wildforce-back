<?php

namespace Database\Factories;

use App\Models\TrainingPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingPreference>
 */
class TrainingPreferenceFactory extends Factory
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
            'goal' => 'buildMuscle',
            'lifestyle' => 'moderatelyActive',
            'gym_type' => 'commercialGym',
            'general_training_level' => 'intermediate',
            'training_split_preference' => 'upperLower',
            'preferred_workout_duration_minutes' => 60,
            'workout_planner_notes' => 'Prioritza la tècnica i progressa de manera sostenible.',
            'workout_days' => ['monday', 'tuesday', 'thursday', 'saturday'],
            'custom_workout_focuses' => ['upperBody' => 2, 'lowerBody' => 2],
            'movement_restrictions' => [],
            'body_composition_phase' => 'recomposition',
            'skips_warmups' => false,
            'skips_cooldowns' => false,
            'skips_rest_periods' => false,
        ];
    }
}
