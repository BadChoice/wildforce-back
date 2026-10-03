<?php

namespace Database\Factories;

use App\Enums\Equipment;
use App\Models\TrainingLocation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingLocation>
 */
class TrainingLocationFactory extends Factory
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
            'name' => 'Gimnàs principal',
            'location_details' => 'Zona de pes lliure i màquines.',
            'display_address' => fake()->address(),
            'latitude' => fake()->latitude(41.35, 41.45),
            'longitude' => fake()->longitude(2.10, 2.25),
            'is_default' => true,
            'sort_order' => 0,
            'equipment' => [
                Equipment::Dumbbells,
                Equipment::OlympicBarbell,
                Equipment::FlatBench,
                Equipment::CableMachine,
                Equipment::PullUpBar,
            ],
        ];
    }
}
