<?php

namespace App\Http\Resources\Api\Sync;

use App\Models\ExerciseResult;
use App\Models\PlannedExercise;
use App\Models\WorkoutBlock;
use App\Models\WorkoutDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutDaySyncResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WorkoutDay $workoutDay */
        $workoutDay = $this->resource;

        return [
            ...$workoutDay->syncPayload(),
            'blocks' => $workoutDay->blocks
                ->sortBy(['order_index', 'id'])
                ->map(fn (WorkoutBlock $block) => $this->blockPayload($block))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blockPayload(WorkoutBlock $block): array
    {
        return [
            ...$block->syncPayload(),
            'exercises' => $block->exercises
                ->sortBy(['order_index', 'id'])
                ->map(fn (PlannedExercise $exercise) => $this->exercisePayload($exercise))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function exercisePayload(PlannedExercise $exercise): array
    {
        return [
            ...$exercise->syncPayload(),
            'exercise_results' => $exercise->exerciseResults
                ->sortBy(['completed_at', 'id'])
                ->map(fn (ExerciseResult $result) => $result->syncPayload())
                ->values()
                ->all(),
        ];
    }
}
