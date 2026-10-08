<?php

namespace Database\Factories;

use App\Enums\NutritionDayType;
use App\Models\NutritionDay;
use App\Models\NutritionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * @extends Factory<NutritionPlan>
 */
class NutritionPlanFactory extends Factory
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
            'source_workout_plan_id' => null,
            'starts_on' => now()->startOfWeek(),
            'goal' => 'buildMuscle',
            'body_composition_phase' => 'recomposition',
            'daily_calorie_average' => 2400,
            'notes' => 'Pla flexible adaptat als dies d’entrenament.',
        ];
    }

    public function complete(int $dayCount = 7, int $mealCount = 4): static
    {
        return $this->afterCreating(function (NutritionPlan $nutritionPlan) use ($dayCount, $mealCount): void {
            NutritionDay::factory()
                ->count($dayCount)
                ->sequence(fn (Sequence $sequence) => [
                    'date' => $nutritionPlan->starts_on->copy()->addDays($sequence->index),
                    'weekday' => strtolower($nutritionPlan->starts_on->copy()->addDays($sequence->index)->englishDayOfWeek),
                    'day_type' => in_array($sequence->index, [0, 1, 3, 5], true) ? NutritionDayType::Training : NutritionDayType::Rest,
                ])
                ->for($nutritionPlan, 'plan')
                ->withMeals($mealCount)
                ->create();
        });
    }
}
