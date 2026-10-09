<?php

namespace App\Services\Workouts;

use App\Ai\Agents\Workouts\WorkoutPlanGeneratorAgent;
use App\Enums\Equipment;
use App\Enums\Generated\ExerciseCategory;
use App\Enums\Generated\ExerciseTrackingMode;
use App\Enums\MesocyclePhase;
use App\Models\TrainingLocation;
use App\Models\User;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use App\Services\ExerciseCatalog\ExerciseCatalog;
use App\Services\Workouts\Progression\MesocyclePhaseRules;
use App\Services\Workouts\Progression\PhasePrescription;
use App\Services\Workouts\Progression\ProgressionAnalysis;
use App\Services\Workouts\Progression\TrainingHistory;
use App\Services\Workouts\Progression\WorkoutProgressionAnalyzer;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\ObjectSchema;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

final class WorkoutPlanAIGenerator
{
    public function __construct(
        private readonly ExerciseCatalog $exerciseCatalog,
        private readonly WorkoutDayResponseMapper $workoutDayResponseMapper,
    ) {}

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

        $planResponse = $response->toArray();

        $this->validateSetStyleConfigurations(
            $planResponse,
            $context['exercises'],
            $user->trainingPreferences?->general_training_level,
        );

        return $this->planFromResponse($user, $context['analysis']->mesocycleNumber, $planResponse);
    }

    /**
     * Generate and persist a workout plan with its days, blocks, and exercises.
     */
    public function generateAndPersist(User $user): WorkoutPlan
    {
        $plan = $this->generate($user);

        return DB::transaction(function () use ($plan): WorkoutPlan {
            $plan->save();

            foreach ($plan->workoutDays as $day) {
                $plan->workoutDays()->save($day);

                foreach ($day->blocks as $block) {
                    $day->blocks()->save($block);

                    foreach ($block->exercises as $exercise) {
                        $exercise->setAttribute('workout_day_id', $day->getKey());
                        $block->exercises()->save($exercise);
                    }
                }
            }

            return $plan->load([
                'workoutDays.blocks.exercises.exerciseResults',
            ]);
        });
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
        $phaseDescription = $this->phaseDescription($phase, $analysis->mesocycleNumber);
        $phasePrescription = PhasePrescription::describe($phase, $preferences?->goal, $preferences?->body_composition_phase);
        $profiles = $this->markdownList($user->exerciseProfiles
            ->map(fn ($profile): string => implode(' | ', array_filter([
                $profile->exercise,
                $profile->working_weight === null ? null : $profile->working_weight.' kg',
                $profile->max_reps === null ? null : 'historical max reps: '.$profile->max_reps,
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
- Height: {$user->height} cm
- Weight: {$user->weight} kg
- Gender: {$user->gender}
- Preferred language: {$user->language}
- Lifestyle: {$preferences?->lifestyle}
- Gym type: {$preferences?->gym_type}
- Preferred workout days: {$this->list($preferences?->workout_days)}
- Preferred duration per workout: {$preferences?->preferred_workout_duration_minutes} minutes
- Training split: {$preferences?->training_split_preference}
- Custom workout focuses: {$this->json($preferences?->custom_workout_focuses)}
- Equipment: {$this->list($this->equipment($location))}
- Movement restrictions: {$this->list($preferences?->movement_restrictions)}
- Include warmups: {$this->yesNo(! $preferences?->skips_warmups)}
- Include cooldowns: {$this->yesNo(! $preferences?->skips_cooldowns)}
- Include rest periods: {$this->yesNo(! $preferences?->skips_rest_periods)}
- Body composition phase: {$preferences?->body_composition_phase}
- Coach notes: {$preferences?->workout_planner_notes}

## Progression context
- Next plan number: {$analysis->mesocycleNumber}
- Phase: {$phaseDescription}
- Readiness: {$analysis->readinessLevel}
- Completion rate: {$analysis->completionRate}
- Overall volume trend: {$analysis->overallVolumeTrend}
- Exercise trends: {$this->associativeList($analysis->exerciseTrends)}
- Keep as anchors: {$this->list(array_keys(array_filter($analysis->exerciseTrends, fn (string $trend): bool => in_array($trend, ['improving', 'plateau'], true))))}
- Rotate when practical: {$this->list($analysis->staleExercises)}
- Underworked muscles: {$this->list($analysis->neglectedMuscleGroups)}
- Overworked muscles: {$this->list($analysis->overworkedMuscleGroups)}

## Phase prescription
Default working parameters for this week. Deviate only when readiness, recent performance, or the duration budget requires a more conservative choice.
{$phasePrescription}

## Exercise profiles
{$profiles}

## Recent active workout performance
Use this section to prescribe sets, reps, and loads. It excludes deload sessions.
{$this->markdownList(explode("\n", $this->recentWorkoutHistory($trainingHistory)))}

## Recent deload history
Use this section only as recovery context. Do not use its loads or reps as the baseline for the next plan.
{$this->markdownList(explode("\n", $this->recentWorkoutHistory($trainingHistory, deloadOnly: true)))}

## Allowed exercises
```text
ID | name | target metrics | movement patterns | planning note
{$exerciseList}
```

Create exactly one workout for each preferred workout day.
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  Collection<int, array<string, mixed>>  $exercises
     */
    private function validateSetStyleConfigurations(array $response, Collection $exercises, ?string $trainingLevel): void
    {
        if (! in_array($trainingLevel, ['intermediate', 'advanced'], true)) {
            return;
        }

        $exercisesById = $exercises->keyBy('id');

        foreach ($response['workoutDays'] as $workoutDay) {
            foreach ($workoutDay['blocks'] as $block) {
                foreach ($block['exercises'] as $plannedExercise) {
                    $exercise = $exercisesById->get($plannedExercise['exercise']);

                    if (! $this->requiresSetStyleConfiguration($block, $plannedExercise, $exercise)) {
                        continue;
                    }

                    $configuration = $plannedExercise['setStyleConfiguration'] ?? null;

                    if (! is_array($configuration) || ! isset($configuration['style']) || ! isset($configuration['target_rir'])) {
                        throw new RuntimeException("Workout plan response requires a set-style configuration with target RIR for {$plannedExercise['exercise']}.");
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $plannedExercise
     * @param  array<string, mixed>|null  $exercise
     */
    private function requiresSetStyleConfiguration(array $block, array $plannedExercise, ?array $exercise): bool
    {
        return in_array($block['type'], ['standard', 'superset'], true)
            && isset($plannedExercise['sets'])
            && ($exercise['trackingMode'] ?? null) === ExerciseTrackingMode::Reps->value
            && ! in_array($exercise['category'] ?? null, [ExerciseCategory::Cardio->value, ExerciseCategory::Mobility->value], true);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function availableExercises(User $user): Collection
    {
        $preferences = $user->trainingPreferences;
        $location = $user->trainingLocations->firstWhere('is_default', true) ?? $user->trainingLocations->first();
        $equipment = $this->equipment($location) ?? [Equipment::Bodyweight->value];
        $restrictions = $preferences?->movement_restrictions ?? [];
        $needsMobilityExercises = $preferences?->needsMobilityExercises() ?? true;

        return collect($this->exerciseCatalog->all()['exercises'] ?? [])
            ->filter(fn (mixed $exercise): bool => is_array($exercise))
            ->filter(function (array $exercise) use ($preferences, $equipment, $restrictions, $needsMobilityExercises): bool {
                $requiredEquipment = $exercise['requiredEquipment'] ?? [];
                $compatibleGoals = $exercise['compatibleGoals'] ?? [];
                $contraindications = $exercise['contraindicatedRestrictions'] ?? [];

                return array_diff($requiredEquipment, $equipment) === []
                    && ($compatibleGoals === [] || in_array($preferences?->goal, $compatibleGoals, true))
                    && array_intersect($contraindications, $restrictions) === []
                    && ($needsMobilityExercises || ($exercise['category'] ?? null) !== ExerciseCategory::Mobility->value);
            })
            ->sortBy('id')
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
        $day = $this->workoutDayResponseMapper->make($user, $response, [
            'order_index' => $dayIndex,
        ]);
        $day->setRelation('plan', $plan);

        return $day;
    }

    /**
     * @return list<string>|null
     */
    private function equipment(?TrainingLocation $location): ?array
    {
        return $location?->equipment?->map(fn (Equipment $item): string => $item->value)->values()->all();
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

    /**
     * @param  array{phase: MesocyclePhase, weekInPhase: int, cycleLength: int, positionInCycle: int}|null  $phase
     */
    private function phaseDescription(?array $phase, int $mesocycleNumber): string
    {
        if ($phase === null) {
            return 'Not periodized';
        }

        $phaseLength = MesocyclePhaseRules::phaseLength($phase['phase'], $phase['cycleLength']);
        $nextPhase = MesocyclePhaseRules::phaseInfoForCycleLength($mesocycleNumber + 1, $phase['cycleLength'])['phase'] ?? null;

        return $phase['phase']->value
            .' week '.$phase['weekInPhase'].' of '.$phaseLength
            .' (cycle week '.$phase['positionInCycle'].' of '.$phase['cycleLength'].')'
            .($nextPhase === null || $nextPhase === $phase['phase'] ? '' : '. Next week: '.$nextPhase->value);
    }

    private function recentWorkoutHistory(TrainingHistory $trainingHistory, bool $deloadOnly = false): string
    {
        return $trainingHistory->recentPlans()
            ->filter(fn ($plan): bool => $deloadOnly ? $plan->phase === MesocyclePhase::Deload : $plan->phase !== MesocyclePhase::Deload)
            ->map(function ($plan): string {
                $workouts = $plan->workoutDays
                    ->map(function ($workoutDay): string {
                        $exercises = $workoutDay->exercises
                            ->map(function ($exercise): string {
                                $result = $exercise->exerciseResults->last();

                                return $exercise->exercise.($result === null
                                    ? ''
                                    : ' ('.$result->completed_sets.' sets × '.$result->completed_reps.' reps @ '.$result->completed_weight.' kg; '.$result->feedback->value.')');
                            })
                            ->implode(', ');

                        return $workoutDay->status.': '.$exercises;
                    })
                    ->implode(' | ');

                return 'Plan '.$plan->mesocycle_number.' ('.$plan->phase?->value.'): '.$workouts;
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
