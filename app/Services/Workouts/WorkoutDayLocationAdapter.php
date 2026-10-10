<?php

namespace App\Services\Workouts;

use App\Ai\Agents\Workouts\AdaptWorkoutDayLocationAgent;
use App\Enums\Equipment;
use App\Enums\WorkoutBlockType;
use App\Models\PlannedExercise;
use App\Models\TrainingLocation;
use App\Models\WorkoutBlock;
use App\Models\WorkoutDay;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;

final class WorkoutDayLocationAdapter extends SingleWorkoutAIGenerator
{
    /**
     * Generate a non-persisted copy of the workout day adapted to the equipment of another training location.
     */
    public function adapt(WorkoutDay $workoutDay, TrainingLocation $location): WorkoutDay
    {
        $workoutDay->loadMissing(['user.trainingPreferences', 'blocks.exercises']);
        $user = $workoutDay->user;

        return $this->generateWorkout($user, [
            'goal' => null,
            'focuses' => [$workoutDay->focus->value],
            'muscleGroups' => [],
            'equipment' => $location->equipment->map(fn (Equipment $equipment): string => $equipment->value)->values()->all(),
            'includeWarmup' => $this->hasExercisesInBlockType($workoutDay, WorkoutBlockType::Warmup),
            'includeCooldown' => $this->hasExercisesInBlockType($workoutDay, WorkoutBlockType::Cooldown),
            'durationMinutes' => $workoutDay->estimated_duration_minutes ?? $user->trainingPreferences?->preferred_workout_duration_minutes ?? 50,
        ], $this->adaptationContext($workoutDay, $location));
    }

    /** @param list<string> $exerciseIds */
    protected function agent(array $exerciseIds): Agent
    {
        return new AdaptWorkoutDayLocationAgent($exerciseIds);
    }

    private function hasExercisesInBlockType(WorkoutDay $workoutDay, WorkoutBlockType $type): bool
    {
        return $workoutDay->blocks->contains(fn (WorkoutBlock $block): bool => $block->type === $type && $block->exercises->isNotEmpty());
    }

    private function adaptationContext(WorkoutDay $workoutDay, TrainingLocation $location): string
    {
        $exercises = $workoutDay->blocks->sortBy('order_index')
            ->flatMap(fn (WorkoutBlock $block): Collection => $block->exercises->sortBy('order_index')->map(fn (PlannedExercise $exercise): string => implode(' | ', [
                $block->type->value,
                $exercise->exercise,
                $exercise->sets ?? '-',
                $exercise->reps_min === null ? '-' : $exercise->reps_min.'-'.($exercise->reps_max ?? $exercise->reps_min),
                $exercise->target_weight_kg === null ? '-' : $exercise->target_weight_kg.' kg',
                $exercise->target_duration_seconds === null ? ($exercise->target_duration_minutes === null ? '-' : $exercise->target_duration_minutes.' min') : $exercise->target_duration_seconds.' s',
                $exercise->rest_seconds === null ? '-' : $exercise->rest_seconds.' s',
            ])))
            ->implode("\n") ?: 'No exercises are currently available.';

        return <<<PROMPT
## Adaptation objective
- Adapt the existing workout day below to the training location "{$location->name}".
- Keep the same core training intent: similar focus, movement patterns, and approximate session difficulty.
- Use only the equipment available at this location; prefer close safe substitutions over changing the whole session structure.
- Preserve the total duration as closely as practical.

## Original workout day
- Title: {$workoutDay->title}
- Focus: {$workoutDay->focus->value}
- Day type: {$workoutDay->day_type?->value}
- Estimated duration: {$workoutDay->estimated_duration_minutes} minutes
- Notes: {$workoutDay->notes}

```text
block | exercise ID | sets | reps | load | duration | rest
{$exercises}
```
PROMPT;
    }
}
