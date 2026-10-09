<?php

namespace Database\Factories;

use App\Ai\Agents\Nutrition\MacrosFromTextAgent;
use App\Models\AiUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiUsage>
 */
class AiUsageFactory extends Factory
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
            'invocation_id' => (string) Str::uuid7(),
            'agent' => MacrosFromTextAgent::class,
            'provider' => 'openai',
            'model' => 'gpt-6-luna',
            'input_tokens' => fake()->numberBetween(500, 5000),
            'output_tokens' => fake()->numberBetween(100, 2000),
            'cost_micros' => fake()->numberBetween(100, 5000),
        ];
    }
}
