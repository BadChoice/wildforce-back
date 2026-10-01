<?php

namespace App\Http\Resources\Api\Workouts;

use App\Models\PlannedExercise;
use App\Models\WorkoutBlock;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkoutPlan */
class WorkoutPlanGenerationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WorkoutPlan $plan */
        $plan = $this->resource;

        return [
            'name' => $plan->name,
            'goal' => $plan->goal,
            'status' => $plan->status,
            'notes' => $plan->notes,
            'mesocycle_number' => $plan->mesocycle_number,
            'phase' => $plan->phase,
            'phase_week' => $plan->phase_week,
            'cycle_length' => $plan->cycle_length,
            'body_composition_phase' => $plan->body_composition_phase,
            'starts_on' => $plan->starts_on?->toISOString(),
            'workout_days' => $plan->workoutDays
                ->map(fn (WorkoutDay $workoutDay): array => $this->workoutDay($workoutDay))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function workoutDay(WorkoutDay $workoutDay): array
    {
        return [
            'title' => $workoutDay->title,
            'focus' => $workoutDay->focus,
            'status' => $workoutDay->status,
            'order_index' => $workoutDay->order_index,
            'intended_weekday' => $workoutDay->intended_weekday,
            'day_type' => $workoutDay->day_type,
            'estimated_duration_minutes' => $workoutDay->estimated_duration_minutes,
            'notes' => $workoutDay->notes,
            'blocks' => $workoutDay->blocks
                ->map(fn (WorkoutBlock $block): array => $this->block($block))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function block(WorkoutBlock $block): array
    {
        return [
            'type' => $block->type,
            'order_index' => $block->order_index,
            'rounds' => $block->rounds,
            'rest_after_block_seconds' => $block->rest_after_block_seconds,
            'notes' => $block->notes,
            'exercises' => $block->exercises
                ->map(fn (PlannedExercise $exercise): array => $this->exercise($exercise))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function exercise(PlannedExercise $exercise): array
    {
        return [
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
            'set_style_configuration' => $exercise->set_style_configuration?->toArray(),
            'notes' => $exercise->notes,
        ];
    }
}
