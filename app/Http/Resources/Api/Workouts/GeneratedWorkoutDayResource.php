<?php

namespace App\Http\Resources\Api\Workouts;

use App\Models\PlannedExercise;
use App\Models\WorkoutBlock;
use App\Models\WorkoutDay;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GeneratedWorkoutDayResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WorkoutDay $workoutDay */
        $workoutDay = $this->resource;

        return [
            'title' => $workoutDay->title,
            'focus' => $workoutDay->focus->value,
            'status' => $workoutDay->status,
            'order_index' => $workoutDay->order_index,
            'intended_weekday' => $workoutDay->intended_weekday,
            'day_type' => $workoutDay->day_type?->value,
            'estimated_duration_minutes' => $workoutDay->estimated_duration_minutes,
            'creation_source' => $workoutDay->creation_source,
            'notes' => $workoutDay->notes,
            'blocks' => $workoutDay->blocks->sortBy('order_index')->map(fn (WorkoutBlock $block): array => [
                'type' => $block->type,
                'order_index' => $block->order_index,
                'rounds' => $block->rounds,
                'rest_after_block_seconds' => $block->rest_after_block_seconds,
                'notes' => $block->notes,
                'exercises' => $block->exercises->sortBy('order_index')->map(fn (PlannedExercise $exercise): array => [
                    'exercise' => $exercise->exercise,
                    'order_index' => $exercise->order_index,
                    'sets' => $exercise->sets,
                    'reps_min' => $exercise->reps_min,
                    'reps_max' => $exercise->reps_max,
                    'target_reps' => $exercise->target_reps,
                    'target_weight_kg' => $exercise->target_weight_kg,
                    'target_weights_kg' => $exercise->target_weights_kg,
                    'target_duration_minutes' => $exercise->target_duration_minutes,
                    'target_duration_seconds' => $exercise->target_duration_seconds,
                    'target_distance_km' => $exercise->target_distance_km,
                    'target_pace_seconds_per_km' => $exercise->target_pace_seconds_per_km,
                    'rest_seconds' => $exercise->rest_seconds,
                    'set_style_configuration' => $exercise->set_style_configuration,
                    'skipped_at' => $exercise->skipped_at?->toISOString(),
                    'notes' => $exercise->notes,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
