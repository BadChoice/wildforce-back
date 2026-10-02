<?php

namespace App\Services\Workouts;

use App\Models\PlannedExercise;
use App\Models\User;
use App\Models\WorkoutBlock;
use App\Models\WorkoutDay;

final class WorkoutDayResponseMapper
{
    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $attributes
     */
    public function make(User $user, array $response, array $attributes = []): WorkoutDay
    {
        $workoutDay = new WorkoutDay;
        $workoutDay->forceFill(array_merge([
            'user_id' => $user->id,
            'title' => $response['title'],
            'focus' => $response['focus'],
            'status' => 'planned',
            'order_index' => 0,
            'intended_weekday' => $response['intendedWeekday'] ?? null,
            'day_type' => $response['dayType'],
            'estimated_duration_minutes' => $response['estimatedDurationMinutes'],
            'creation_source' => 'generated',
            'notes' => $response['notes'] ?? null,
        ], $attributes));
        $workoutDay->setRelation('user', $user);
        $workoutDay->setRelation('blocks', collect($response['blocks'])->values()->map(
            fn (array $block, int $blockIndex): WorkoutBlock => $this->block($workoutDay, $block, $blockIndex),
        ));

        return $workoutDay;
    }

    /** @param array<string, mixed> $response */
    private function block(WorkoutDay $workoutDay, array $response, int $index): WorkoutBlock
    {
        $block = new WorkoutBlock;
        $block->forceFill([
            'type' => $response['type'],
            'order_index' => $index,
            'rounds' => $response['rounds'],
            'rest_after_block_seconds' => $response['restAfterBlockSeconds'] ?? null,
            'notes' => $response['notes'] ?? null,
        ]);
        $block->setRelation('workoutDay', $workoutDay);
        $block->setRelation('exercises', collect($response['exercises'])->values()->map(
            fn (array $exercise, int $exerciseIndex): PlannedExercise => $this->exercise($workoutDay, $block, $exercise, $exerciseIndex),
        ));

        return $block;
    }

    /** @param array<string, mixed> $response */
    private function exercise(WorkoutDay $workoutDay, WorkoutBlock $block, array $response, int $index): PlannedExercise
    {
        $exercise = new PlannedExercise;
        $exercise->forceFill([
            'exercise' => $response['exercise'],
            'order_index' => $index,
            'sets' => $response['sets'] ?? null,
            'reps_min' => $response['repsMin'] ?? null,
            'reps_max' => $response['repsMax'] ?? null,
            'target_reps' => $response['targetReps'] ?? null,
            'target_weight_kg' => $response['targetWeightKg'] ?? null,
            'target_weights_kg' => $response['targetWeightsKg'] ?? null,
            'target_duration_minutes' => $response['targetDurationMinutes'] ?? null,
            'target_duration_seconds' => $response['targetDurationSeconds'] ?? null,
            'target_distance_km' => $response['targetDistanceKm'] ?? null,
            'target_pace_seconds_per_km' => $response['targetPaceSecondsPerKm'] ?? null,
            'rest_seconds' => $response['restSeconds'] ?? null,
            'set_style_configuration' => $response['setStyleConfiguration'] ?? null,
            'notes' => $response['notes'] ?? null,
        ]);
        $exercise->setRelation('workoutDay', $workoutDay);
        $exercise->setRelation('block', $block);

        return $exercise;
    }
}
