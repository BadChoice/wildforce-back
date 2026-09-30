<?php

namespace Database\Factories;

use App\Enums\WorkoutKind;
use App\Models\User;
use App\Models\WorkoutBlock;
use App\Models\WorkoutDay;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * @extends Factory<WorkoutDay>
 */
class WorkoutDayFactory extends Factory
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
            'kind' => WorkoutKind::Workout,
            'title' => 'Full body',
            'focus' => 'fullBody',
            'status' => 'planned',
            'did_count_toward_streak' => false,
            'order_index' => 0,
            'estimated_duration_minutes' => 60,
            'creation_source' => 'generated',
        ];
    }

    public function withBlocks(int $blockCount = 2, int $exerciseCount = 3): static
    {
        return $this->afterCreating(function (WorkoutDay $workoutDay) use ($blockCount, $exerciseCount): void {
            WorkoutBlock::factory()
                ->count($blockCount)
                ->sequence(fn (Sequence $sequence) => ['order_index' => $sequence->index])
                ->for($workoutDay, 'workoutDay')
                ->withExercises($exerciseCount)
                ->create();
        });
    }
}
