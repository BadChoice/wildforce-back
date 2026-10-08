<?php

namespace App\Services\Workouts;

use App\Ai\Agents\Workouts\SingleWorkoutAgent;
use App\Enums\WorkoutBlockType;
use App\Models\User;
use App\Models\WorkoutDay;
use App\Services\ExerciseCatalog\ExerciseCatalog;
use Illuminate\Support\Collection;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

final class SingleWorkoutAIGenerator
{
    public function __construct(
        private readonly ExerciseCatalog $exerciseCatalog,
        private readonly WorkoutDayResponseMapper $workoutDayResponseMapper,
    ) {}

    /** @param array{goal: string|null, focuses: list<string>, muscleGroups: list<string>, equipment: list<string>, includeWarmup: bool, includeCooldown: bool, durationMinutes: int} $request */
    public function generate(User $user, array $request): WorkoutDay
    {
        $user->loadMissing(['trainingPreferences', 'exerciseProfiles', 'workoutPlans.workoutDays.exercises.exerciseResults']);
        $goal = $request['goal'] ?? $user->trainingPreferences?->goal ?? 'generalFitness';
        $exercises = $this->availableExercises($user, $goal, $request['equipment']);

        if ($exercises->isEmpty()) {
            throw new RuntimeException('Single workout generation requires compatible exercises for the requested equipment.');
        }

        $response = (new SingleWorkoutAgent($exercises->pluck('id')->all()))->prompt($this->prompt($user, $request, $goal, $exercises));

        if (! $response instanceof StructuredAgentResponse) {
            throw new RuntimeException('The single workout generator did not return structured data.');
        }

        $workout = $this->workoutDayResponseMapper->make($user, $response->toArray());
        $this->validateSetStyleConfigurations($workout, $exercises, $user->trainingPreferences?->general_training_level);

        return $workout;
    }

    /** @param list<string> $equipment
     * @return Collection<int, array<string, mixed>>
     */
    private function availableExercises(User $user, string $goal, array $equipment): Collection
    {
        $restrictions = $user->trainingPreferences?->movement_restrictions ?? [];

        return collect($this->exerciseCatalog->all()['exercises'] ?? [])
            ->filter(fn (mixed $exercise): bool => is_array($exercise))
            ->filter(function (array $exercise) use ($goal, $equipment, $restrictions): bool {
                return array_diff($exercise['requiredEquipment'] ?? [], $equipment) === []
                    && (($exercise['compatibleGoals'] ?? []) === [] || in_array($goal, $exercise['compatibleGoals'], true))
                    && array_intersect($exercise['contraindicatedRestrictions'] ?? [], $restrictions) === [];
            })
            ->sortBy('id')
            ->take(60)
            ->values();
    }

    /** @param array{goal: string|null, focuses: list<string>, muscleGroups: list<string>, equipment: list<string>, includeWarmup: bool, includeCooldown: bool, durationMinutes: int} $request
     * @param  Collection<int, array<string, mixed>>  $exercises
     */
    private function prompt(User $user, array $request, string $goal, Collection $exercises): string
    {
        $preferences = $user->trainingPreferences;
        $profiles = $user->exerciseProfiles->sortBy('exercise')->map(fn ($profile): string => implode(' | ', array_filter([$profile->exercise, $profile->working_weight === null ? null : $profile->working_weight.' kg', $profile->max_reps === null ? null : 'historical max reps: '.$profile->max_reps])))->implode("\n") ?: 'None';
        $exerciseList = $exercises->map(fn (array $exercise): string => implode(' | ', array_filter([$exercise['id'], $exercise['name'], 'targets: '.implode(', ', $exercise['targetMetrics'] ?? []), implode(', ', $exercise['movementPatterns'] ?? []), $exercise['notesForLlm'] ?? null])))->implode("\n");

        return <<<PROMPT
## Single workout request
- Goal: {$goal}
- Focus: {$this->list($request['focuses'])}
- Target muscle groups: {$this->list($request['muscleGroups'])}
- Available equipment for this session: {$this->list($request['equipment'])}
- Target duration: {$request['durationMinutes']} minutes total
- Include warmup: {$this->yesNo($request['includeWarmup'])}
- Include cooldown: {$this->yesNo($request['includeCooldown'])}

## Client context
- Training level: {$preferences?->general_training_level}
- Age: {$user->birth_date?->age}
- Height: {$user->height} cm
- Weight: {$user->weight} kg
- Gender: {$user->gender}
- Preferred language: {$user->language}
- Lifestyle: {$preferences?->lifestyle}
- Movement restrictions: {$this->list($preferences?->movement_restrictions ?? [])}
- Coach notes: {$preferences?->workout_planner_notes}

## Exercise profiles
{$profiles}

## Recent workout history
{$this->recentWorkoutHistory($user)}

## Allowed exercises
```text
ID | name | target metrics | movement patterns | planning note
{$exerciseList}
```

Create exactly one workout day. Include warmup and cooldown blocks only when requested. Do not use empty, hidden, or placeholder blocks.
PROMPT;
    }

    /** @param Collection<int, array<string, mixed>> $exercises */
    private function validateSetStyleConfigurations(WorkoutDay $workout, Collection $exercises, ?string $trainingLevel): void
    {
        if (! in_array($trainingLevel, ['intermediate', 'advanced'], true)) {
            return;
        }

        $exercisesById = $exercises->keyBy('id');

        foreach ($workout->blocks as $block) {
            foreach ($block->exercises as $plannedExercise) {
                $exercise = $exercisesById->get($plannedExercise->exercise);
                $requiresConfiguration = in_array($block->type, [WorkoutBlockType::Standard, WorkoutBlockType::Superset], true) && $plannedExercise->sets !== null && ($exercise['trackingMode'] ?? null) === 'reps' && ! in_array($exercise['category'] ?? null, ['cardio', 'mobility'], true);

                if ($requiresConfiguration && ($plannedExercise->set_style_configuration === null || $plannedExercise->set_style_configuration->targetRir === null)) {
                    throw new RuntimeException("Single workout response requires a set-style configuration with target RIR for {$plannedExercise->exercise}.");
                }
            }
        }
    }

    private function recentWorkoutHistory(User $user): string
    {
        $plan = $user->workoutPlans->sortByDesc('created_at')->first();

        return $plan === null
            ? '- No previous workout plans are available.'
            : ($plan->workoutDays->map(fn (WorkoutDay $day): string => '- '.$day->title.' ('.$day->focus->value.'): '.$day->exercises->map(fn (PlannedExercise $exercise): string => $exercise->exercise)->implode(', '))->implode("\n") ?: '- No previous workout days are available.');
    }

    /** @param list<string> $values */
    private function list(array $values): string
    {
        return $values === [] ? 'None' : implode(', ', $values);
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'Yes' : 'No';
    }
}
