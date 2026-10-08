<?php

namespace Database\Factories;

use App\Enums\WorkoutBlockType;
use App\Models\PlannedExercise;
use App\Models\WorkoutBlock;
use App\Models\WorkoutDay;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * @extends Factory<WorkoutBlock>
 */
class WorkoutBlockFactory extends Factory
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
            'type' => WorkoutBlockType::Standard,
            'order_index' => 0,
            'rounds' => 1,
        ];
    }

    public function withExercises(int $exerciseCount = 3): static
    {
        return $this->afterCreating(function (WorkoutBlock $workoutBlock) use ($exerciseCount): void {
            PlannedExercise::factory()
                ->count($exerciseCount)
                ->sequence(fn (Sequence $sequence) => ['order_index' => $sequence->index])
                ->for($workoutBlock->workoutDay, 'workoutDay')
                ->for($workoutBlock, 'block')
                ->create();
        });
    }
}
