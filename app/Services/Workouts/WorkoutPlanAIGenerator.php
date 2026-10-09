<?php

namespace App\Services\Workouts;

use App\Ai\Agents\Workouts\WorkoutPlanGeneratorAgent;
use App\Enums\Equipment;
use App\Enums\ExerciseStatus;
use App\Enums\Generated\ExerciseCategory;
use App\Enums\Generated\ExerciseTrackingMode;
use App\Enums\MesocyclePhase;
use App\Models\ExerciseProfile;
use App\Models\ExerciseResult;
use App\Models\PlannedExercise;
use App\Models\TrainingLocation;
use App\Models\User;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use App\Services\ExerciseCatalog\ExerciseCatalog;
use App\Services\Workouts\Progression\ExerciseStatusResolver;
use App\Services\Workouts\Progression\ExerciseTrendCalculator;
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
        $profiles = $this->exerciseProfiles($user, $trainingHistory);
        $exerciseStatuses = $this->exerciseStatusTable(
            new ExerciseStatusResolver($trainingHistory, $this->exerciseCatalog)->resolve($analysis, $this->startsNewPhase($phase)),
        );
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
- Height: {$this->measurement($user->height, 'cm')}
- Weight: {$this->measurement($user->weight, 'kg')}
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
- Completion rate: {$this->percentage($analysis->completionRate)}
- Overall volume trend: {$analysis->overallVolumeTrend}
- Underworked muscles: {$this->list($analysis->neglectedMuscleGroups)}
- Overworked muscles: {$this->list($analysis->overworkedMuscleGroups)}

## Exercise status
Recently trained resistance exercises. Exercises not listed can be chosen freely.
```text
exercise | status | last performance (best set) | trend
{$exerciseStatuses}
```

## Phase prescription
Default working parameters for this week. Deviate only when readiness, recent performance, or the duration budget requires a more conservative choice.
{$phasePrescription}

## Exercise profiles
Self-reported starting points for exercises without recent performance.
{$profiles}

## Recent active workout performance
Use this section to prescribe sets, reps, and loads. It excludes deload sessions. Each exercise shows what was prescribed, then what was done and how it felt.
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
            ->filter(fn (WorkoutPlan $plan): bool => $deloadOnly ? $plan->phase === MesocyclePhase::Deload : $plan->phase !== MesocyclePhase::Deload)
            ->map(function (WorkoutPlan $plan): ?string {
                $workouts = $plan->workoutDays
                    ->filter(fn (WorkoutDay $workoutDay): bool => $workoutDay->exercises->contains(fn (PlannedExercise $exercise): bool => $exercise->exerciseResults->isNotEmpty()))
                    ->map(fn (WorkoutDay $workoutDay): string => $workoutDay->focus?->value.' ('.$workoutDay->status.'): '.$workoutDay->exercises
                        ->map(fn (PlannedExercise $exercise): string => $this->exercisePerformance($exercise))
                        ->implode(', '));

                return $workouts->isEmpty()
                    ? null
                    : 'Plan '.$plan->mesocycle_number.' ('.$plan->phase?->value.'): '.$workouts->implode(' | ');
            })
            ->filter()
            ->implode("\n") ?: 'None';
    }

    private function exercisePerformance(PlannedExercise $exercise): string
    {
        $prescription = $exercise->sets === null || $exercise->reps_min === null
            ? null
            : $exercise->sets.'×'.$this->repRange($exercise->reps_min, $exercise->reps_max)
                .((float) $exercise->target_weight_kg > 0 ? ' @ '.$this->decimal($exercise->target_weight_kg).' kg' : '');
        $result = $exercise->exerciseResults->last();
        $performance = $result === null
            ? 'not done'
            : 'did '.$result->completed_sets.'×'.$result->completed_reps
                .((float) $result->completed_weight > 0 ? ' @ '.$this->decimal($result->completed_weight).' kg' : '')
                .', '.$result->feedback->value;

        return $exercise->exercise.' ('.($prescription === null ? '' : 'planned '.$prescription.'; ').$performance.')';
    }

    /**
     * Profiles only for exercises without recent performance, which is a more reliable baseline.
     */
    private function exerciseProfiles(User $user, TrainingHistory $trainingHistory): string
    {
        $recentlyPerformed = $trainingHistory->recentPlans()
            ->flatMap(fn (WorkoutPlan $plan) => $plan->workoutDays)
            ->flatMap(fn (WorkoutDay $workoutDay) => $workoutDay->exercises)
            ->filter(fn (PlannedExercise $exercise): bool => $exercise->exerciseResults->isNotEmpty())
            ->pluck('exercise')
            ->unique()
            ->all();

        return $this->markdownList($user->exerciseProfiles
            ->reject(fn (ExerciseProfile $profile): bool => in_array($profile->exercise, $recentlyPerformed, true))
            ->map(function (ExerciseProfile $profile): ?string {
                $details = array_filter([
                    (float) $profile->working_weight > 0 ? $this->decimal($profile->working_weight).' kg' : null,
                    $profile->max_reps > 0 ? 'historical max reps: '.$profile->max_reps : null,
                    $profile->preferred_rep_range_min === null || $profile->preferred_rep_range_max === null
                        ? null
                        : $profile->preferred_rep_range_min.'-'.$profile->preferred_rep_range_max.' reps',
                ]);

                return $details === [] ? null : implode(' | ', [$profile->exercise, ...$details]);
            })
            ->filter()
            ->values()
            ->all());
    }

    /**
     * @param  array<string, array{status: ExerciseStatus, trend: string, lastResult: ExerciseResult}>  $exerciseStatuses
     */
    private function exerciseStatusTable(array $exerciseStatuses): string
    {
        $trendCalculator = new ExerciseTrendCalculator;

        return collect($exerciseStatuses)
            ->map(function (array $exerciseStatus, string $exercise) use ($trendCalculator): string {
                $result = $exerciseStatus['lastResult'];
                $bestSet = $trendCalculator->bestSet($result);
                $sets = $result->completed_sets ?? count($result->per_set_reps ?? []);
                $performance = $bestSet === null
                    ? $result->feedback->value
                    : $sets.'×'.$bestSet['reps'].($bestSet['weight'] > 0 ? ' @ '.$this->decimal($bestSet['weight']).' kg' : '').', '.$result->feedback->value;

                return implode(' | ', [$exercise, $exerciseStatus['status']->value, $performance, $exerciseStatus['trend']]);
            })
            ->implode("\n") ?: 'None';
    }

    /**
     * Non-periodized plans can rotate any week; periodized plans only at the start of a training phase.
     *
     * @param  array{phase: MesocyclePhase, weekInPhase: int, cycleLength: int, positionInCycle: int}|null  $phase
     */
    private function startsNewPhase(?array $phase): bool
    {
        return $phase === null || ($phase['weekInPhase'] === 1 && $phase['phase'] !== MesocyclePhase::Deload);
    }

    private function repRange(int $minimum, ?int $maximum): string
    {
        return $maximum === null || $maximum === $minimum ? (string) $minimum : $minimum.'-'.$maximum;
    }

    private function decimal(string|float|int $weight): string
    {
        return rtrim(rtrim(number_format((float) $weight, 2, '.', ''), '0'), '.');
    }

    private function measurement(string|int|null $value, string $unit): string
    {
        return (float) $value > 0 ? $this->decimal($value).' '.$unit : 'unknown';
    }

    private function percentage(float $ratio): string
    {
        return round($ratio * 100).'%';
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
