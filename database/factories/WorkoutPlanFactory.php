<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * @extends Factory<WorkoutPlan>
 */
class WorkoutPlanFactory extends Factory
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
            'name' => fake()->words(3, true),
            'goal' => 'generalFitness',
            'status' => 'active',
            'starts_on' => now()->startOfWeek(),
            'cycle_length' => 4,
        ];
    }

    public function complete(int $workoutDayCount = 3, int $blockCount = 2, int $exerciseCount = 3): static
    {
        return $this->afterCreating(function (WorkoutPlan $workoutPlan) use ($workoutDayCount, $blockCount, $exerciseCount): void {
            WorkoutDay::factory()
                ->count($workoutDayCount)
                ->sequence(fn (Sequence $sequence) => [
                    'order_index' => $sequence->index,
                    'scheduled_for' => $workoutPlan->starts_on?->copy()->addDays($sequence->index),
                ])
                ->for($workoutPlan->user)
                ->for($workoutPlan, 'plan')
                ->withBlocks($blockCount, $exerciseCount)
                ->create();
        });
    }
}
