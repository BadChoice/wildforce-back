<?php

namespace Database\Factories;

use App\Models\NutritionLogMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NutritionLogMedia>
 */
class NutritionLogMediaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => 'localPhoto',
            'local_relative_path' => null,
            'remote_url' => null,
        ];
    }
}
