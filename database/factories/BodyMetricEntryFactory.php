<?php

namespace Database\Factories;

use App\Models\BodyMetricEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BodyMetricEntry>
 */
class BodyMetricEntryFactory extends Factory
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
            'type' => 'weight',
            'value' => fake()->randomFloat(1, 60, 100),
            'recorded_at' => fake()->dateTimeBetween('-3 months', 'now'),
            'source' => 'manual',
        ];
    }
}
