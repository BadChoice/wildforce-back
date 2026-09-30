<?php

namespace App\Services\Workouts;

use App\Ai\Agents\Workouts\WorkoutPlanGeneratorAgent;
use App\Models\PlannedExercise;
use App\Models\User;
use App\Models\WorkoutBlock;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use App\Services\ExerciseCatalog\ExerciseCatalog;
use App\Services\Workouts\Progression\MesocyclePhaseRules;
use App\Services\Workouts\Progression\ProgressionAnalysis;
use App\Services\Workouts\Progression\TrainingHistory;
use App\Services\Workouts\Progression\WorkoutProgressionAnalyzer;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Collection;
use Laravel\Ai\ObjectSchema;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

final class WorkoutPlanAIGenerator
{
    public function __construct(private readonly ExerciseCatalog $exerciseCatalog) {}

    public function generate(User $user): WorkoutPlan
    {
        $context = $this->generationContext($user);
        $agent = $this->agent($context['exercises'], $context['workoutDays']);

        $response = $agent->prompt(
            $this->prompt($user, $context['trainingHistory'], $context['analysis'], $context['exercises']),
        );

        if (! $response instanceof StructuredAgentResponse) {
            throw new RuntimeException('The workout plan generator did not return structured data.');
        }

        return $this->planFromResponse($user, $context['analysis']->mesocycleNumber, $response->toArray());
    }

    /**
     * Return the exact prompt and structured-output schema used for generation,
     * without sending a request to the AI provider.
     *
     * @return array{instructions: string, prompt: string, schema: string}
     */
    public function preview(User $user): array
    {
        $context = $this->generationContext($user);
        $agent = $this->agent($context['exercises'], $context['workoutDays']);
        $schema = json_encode(
            (new ObjectSchema($agent->schema(new JsonSchemaTypeFactory)))->toSchema(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        if (! is_string($schema)) {
            throw new RuntimeException('The workout plan response schema could not be encoded.');
        }

        return [
            'instructions' => $agent->instructions(),
            'prompt' => $this->prompt($user, $context['trainingHistory'], $context['analysis'], $context['exercises']),
            'schema' => $schema,
        ];
    }

    /**
     * @return array{trainingHistory: TrainingHistory, analysis: ProgressionAnalysis, exercises: Collection<int, array<string, mixed>>, workoutDays: list<string>}
     */
    private function generationContext(User $user): array
    {
        $user->loadMissing(['trainingPreferences', 'trainingLocations', 'exerciseProfiles']);

        $trainingHistory = new TrainingHistory($user);
        $analysis = new WorkoutProgressionAnalyzer(
            $trainingHistory,
            $this->exerciseCatalog,
        )->analyze();
        $exercises = $this->availableExercises($user);
        $workoutDays = $user->trainingPreferences?->workout_days ?? [];

        if ($workoutDays === [] || $exercises->isEmpty()) {
            throw new RuntimeException('Workout plan generation requires preferred workout days and compatible exercises.');
        }

        return [
            'trainingHistory' => $trainingHistory,
            'analysis' => $analysis,
            'exercises' => $exercises,
            'workoutDays' => $workoutDays,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $exercises
     * @param  list<string>  $workoutDays
     */
    private function agent(Collection $exercises, array $workoutDays): WorkoutPlanGeneratorAgent
    {
        return new WorkoutPlanGeneratorAgent($exercises->pluck('id')->all(), $workoutDays);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $exercises
     */
    private function prompt(User $user, TrainingHistory $trainingHistory, ProgressionAnalysis $analysis, Collection $exercises): string
    {
        $preferences = $user->trainingPreferences;
        $location = $user->trainingLocations->firstWhere('is_default', true) ?? $user->trainingLocations->first();
        $phase = MesocyclePhaseRules::phaseInfo(
            $analysis->mesocycleNumber,
            $preferences?->general_training_level ?? 'beginner',
        );
        $phaseDescription = $phase === null
            ? 'Not periodized'
            : $phase['phase'].' (week '.$phase['weekInPhase'].' of '.$phase['cycleLength'].')';
        $profiles = $this->markdownList($user->exerciseProfiles
            ->map(fn ($profile): string => implode(' | ', array_filter([
                $profile->exercise,
                $profile->working_weight === null ? null : $profile->working_weight.' kg',
                $profile->preferred_rep_range_min === null || $profile->preferred_rep_range_max === null
                    ? null
                    : $profile->preferred_rep_range_min.'-'.$profile->preferred_rep_range_max.' reps',
            ])))
            ->all());
        $exerciseList = $exercises
            ->map(fn (array $exercise): string => implode(' | ', array_filter([
                $exercise['id'],
                $exercise['name'],
                'targets: '.implode(', ', $exercise['targetMetrics'] ?? []),
                implode(', ', $exercise['movementPatterns'] ?? []),
                $exercise['notesForLlm'] ?? null,
            ])))
            ->implode("\n");

        return <<<PROMPT
## Client context
- Goal: {$preferences?->goal}
- Training level: {$preferences?->general_training_level}
- Age: {$user->birth_date?->age}
- Height: {$user->height_cm} cm
- Weight: {$user->weight_kg} kg
- Gender: {$user->gender}
- Preferred language: {$user->language}
- Lifestyle: {$preferences?->lifestyle}
- Gym type: {$preferences?->gym_type}
- Preferred workout days: {$this->list($preferences?->workout_days)}
- Preferred duration per workout: {$preferences?->preferred_workout_duration_minutes} minutes
- Training split: {$preferences?->training_split_preference}
- Custom workout focuses: {$this->json($preferences?->custom_workout_focuses)}
- Equipment: {$this->list($location?->equipment)}
- Movement restrictions: {$this->list($preferences?->movement_restrictions)}
- Include warmups: {$this->yesNo(! $preferences?->skips_warmups)}
- Include cooldowns: {$this->yesNo(! $preferences?->skips_cooldowns)}
- Include rest periods: {$this->yesNo(! $preferences?->skips_rest_periods)}
- Body composition phase: {$preferences?->body_composition_phase}
- Coach notes: {$preferences?->workout_planner_notes}

## Progression context
- Next plan number: {$analysis->mesocycleNumber}
- Phase: {$phaseDescription}
- Phase prescription: {$this->phaseGuidance($phase['phase'] ?? null, $preferences?->goal)}
- Readiness: {$analysis->readinessLevel}
- Completion rate: {$analysis->completionRate}
- Overall volume trend: {$analysis->overallVolumeTrend}
- Exercise trends: {$this->associativeList($analysis->exerciseTrends)}
- Keep as anchors: {$this->list(array_keys(array_filter($analysis->exerciseTrends, fn (string $trend): bool => in_array($trend, ['improving', 'plateau'], true))))}
- Rotate when practical: {$this->list($analysis->staleExercises)}
- Underworked muscles: {$this->list($analysis->neglectedMuscleGroups)}
- Overworked muscles: {$this->list($analysis->overworkedMuscleGroups)}

## Exercise profiles
{$profiles}

## Recent workout history
{$this->markdownList(explode("\n", $this->recentWorkoutHistory($trainingHistory)))}

## Allowed exercises
```text
ID | name | target metrics | movement patterns | planning note
{$exerciseList}
```

Create exactly one workout for each preferred workout day.
PROMPT;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function availableExercises(User $user): Collection
    {
        $preferences = $user->trainingPreferences;
        $location = $user->trainingLocations->firstWhere('is_default', true) ?? $user->trainingLocations->first();
        $equipment = $location?->equipment ?? ['bodyweight'];
        $restrictions = $preferences?->movement_restrictions ?? [];

        return collect($this->exerciseCatalog->all()['exercises'] ?? [])
            ->filter(fn (mixed $exercise): bool => is_array($exercise))
            ->filter(function (array $exercise) use ($preferences, $equipment, $restrictions): bool {
                $requiredEquipment = $exercise['requiredEquipment'] ?? [];
                $compatibleGoals = $exercise['compatibleGoals'] ?? [];
                $contraindications = $exercise['contraindicatedRestrictions'] ?? [];

                return array_diff($requiredEquipment, $equipment) === []
                    && ($compatibleGoals === [] || in_array($preferences?->goal, $compatibleGoals, true))
                    && array_intersect($contraindications, $restrictions) === [];
            })
            ->sortBy('id')
            ->take(60)
            ->values();
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function planFromResponse(User $user, int $mesocycleNumber, array $response): WorkoutPlan
    {
        $preferences = $user->trainingPreferences;
        $phase = MesocyclePhaseRules::phaseInfo($mesocycleNumber, $preferences?->general_training_level ?? 'beginner');
        $plan = new WorkoutPlan;
        $plan->forceFill([
            'user_id' => $user->id,
            'name' => $response['name'],
            'goal' => $preferences?->goal ?? 'generalFitness',
            'notes' => $response['notes'] ?? null,
            'status' => 'draft',
            'mesocycle_number' => $mesocycleNumber,
            'phase' => $phase['phase'] ?? null,
            'phase_week' => $phase['weekInPhase'] ?? null,
            'cycle_length' => $phase['cycleLength'] ?? null,
            'body_composition_phase' => $preferences?->body_composition_phase,
            'starts_on' => now()->startOfWeek(),
        ]);
        $plan->setRelation('user', $user);
        $plan->setRelation('workoutDays', collect($response['workoutDays'])->values()->map(
            fn (array $day, int $dayIndex): WorkoutDay => $this->workoutDayFromResponse($user, $plan, $day, $dayIndex),
        ));

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function workoutDayFromResponse(User $user, WorkoutPlan $plan, array $response, int $dayIndex): WorkoutDay
    {
        $day = new WorkoutDay;
        $day->forceFill([
            'user_id' => $user->id,
            'title' => $response['title'],
            'focus' => $response['focus'],
            'status' => 'planned',
            'order_index' => $dayIndex,
            'intended_weekday' => $response['intendedWeekday'],
            'day_type' => $response['dayType'],
            'estimated_duration_minutes' => $response['estimatedDurationMinutes'],
            'creation_source' => 'generated',
            'notes' => $response['notes'] ?? null,
        ]);
        $day->setRelation('user', $user);
        $day->setRelation('plan', $plan);
        $day->setRelation('blocks', collect($response['blocks'])->values()->map(
            fn (array $block, int $blockIndex): WorkoutBlock => $this->blockFromResponse($day, $block, $blockIndex),
        ));

        return $day;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function blockFromResponse(WorkoutDay $day, array $response, int $blockIndex): WorkoutBlock
    {
        $block = new WorkoutBlock;
        $block->forceFill([
            'type' => $response['type'],
            'order_index' => $blockIndex,
            'rounds' => $response['rounds'],
            'rest_after_block_seconds' => $response['restAfterBlockSeconds'] ?? null,
            'notes' => $response['notes'] ?? null,
        ]);
        $block->setRelation('workoutDay', $day);
        $block->setRelation('exercises', collect($response['exercises'])->values()->map(
            fn (array $exercise, int $exerciseIndex): PlannedExercise => $this->exerciseFromResponse($day, $block, $exercise, $exerciseIndex),
        ));

        return $block;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function exerciseFromResponse(WorkoutDay $day, WorkoutBlock $block, array $response, int $exerciseIndex): PlannedExercise
    {
        $exercise = new PlannedExercise;
        $exercise->forceFill([
            'exercise' => $response['exercise'],
            'order_index' => $exerciseIndex,
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
        $exercise->setRelation('workoutDay', $day);
        $exercise->setRelation('block', $block);

        return $exercise;
    }

    /**
     * @param  array<int, string>|null  $values
     */
    private function list(?array $values): string
    {
        return $values === [] || $values === null ? 'None' : implode(', ', $values);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function associativeList(array $values): string
    {
        return $values === [] ? 'None' : collect($values)->map(fn (string $value, string $key): string => $key.': '.$value)->implode(', ');
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'Yes' : 'No';
    }

    /**
     * @param  array<string, mixed>|null  $value
     */
    private function json(?array $value): string
    {
        return $value === [] || $value === null ? 'None' : (string) json_encode($value);
    }

    private function phaseGuidance(?string $phase, ?string $goal): string
    {
        return match ($phase) {
            'deload' => 'Reduce working volume by roughly 40% and use conservative loads.',
            'intensification' => $goal === 'gainStrength'
                ? 'Main lifts: 2-5 reps, 82-92% of 1RM, 3-5 minutes rest.'
                : 'Reduce volume, use challenging but controlled loads, and rest 2-3 minutes on main lifts.',
            'accumulation' => $goal === 'gainStrength'
                ? 'Main lifts: 5-8 reps, 75-80% of 1RM, 2-3 minutes rest.'
                : 'Prioritise productive volume with moderate loads, usually 8-15 reps and 60-90 seconds rest.',
            default => 'Build sustainable technique and consistency before increasing load or volume.',
        };
    }

    private function recentWorkoutHistory(TrainingHistory $trainingHistory): string
    {
        return $trainingHistory->recentPlans()
            ->map(function ($plan): string {
                $workouts = $plan->workoutDays
                    ->map(function ($workoutDay): string {
                        $exercises = $workoutDay->exercises
                            ->map(function ($exercise): string {
                                $result = $exercise->exerciseResults->last();

                                return $exercise->exercise.($result === null
                                    ? ''
                                    : ' ('.$result->completed_sets.' sets × '.$result->completed_reps.' reps @ '.$result->completed_weight.' kg; '.$result->feedback.')');
                            })
                            ->implode(', ');

                        return $workoutDay->status.': '.$exercises;
                    })
                    ->implode(' | ');

                return 'Plan '.$plan->mesocycle_number.' ('.$plan->phase.'): '.$workouts;
            })
            ->implode("\n") ?: 'None';
    }

    /**
     * @param  list<string>  $lines
     */
    private function markdownList(array $lines): string
    {
        return $lines === [] ? '- None' : collect($lines)
            ->map(fn (string $line): string => '- '.$line)
            ->implode("\n");
    }
}
