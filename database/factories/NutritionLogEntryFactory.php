<?php

namespace Database\Factories;

use App\Models\NutritionLogEntry;
use App\Models\NutritionLogItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * @extends Factory<NutritionLogEntry>
 */
class NutritionLogEntryFactory extends Factory
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
            'nutrition_log_media_id' => null,
            'title' => fake()->randomElement(['Esmorzar post-entrenament', 'Dinar equilibrat', 'Sopar lleuger']),
            'logged_at' => fake()->dateTimeBetween('-2 weeks', 'now'),
            'meal_type' => fake()->randomElement(['breakfast', 'lunch', 'snack', 'dinner']),
            'notes' => null,
            'is_favorite' => false,
        ];
    }

    public function withItems(int $itemCount = 3): static
    {
        return $this->afterCreating(function (NutritionLogEntry $nutritionLogEntry) use ($itemCount): void {
            NutritionLogItem::factory()
                ->count($itemCount)
                ->sequence(fn (Sequence $sequence) => ['order_index' => $sequence->index])
                ->for($nutritionLogEntry, 'entry')
                ->create();
        });
    }
}
