<?php

namespace App\Services\Workouts\Progression;

use App\Enums\ExerciseFeedback;
use App\Enums\ExerciseStatus;
use App\Enums\Generated\ExerciseCategory;
use App\Models\ExerciseResult;
use App\Services\ExerciseCatalog\ExerciseCatalog;

/**
 * Gives each recently trained resistance exercise one status, so the planner does not have to
 * reconcile trends, continuity, and staleness itself.
 */
final readonly class ExerciseStatusResolver
{
    public function __construct(
        private TrainingHistory $trainingHistory,
        private ExerciseCatalog $exerciseCatalog,
    ) {}

    /**
     * @param  bool  $startsNewPhase  Stale exercises are only rotated when a new phase starts, so a phase keeps its exercise selection.
     * @return array<string, array{status: ExerciseStatus, trend: string, lastResult: ExerciseResult}>
     */
    public function resolve(ProgressionAnalysis $analysis, bool $startsNewPhase): array
    {
        $nonResistanceExercises = collect($this->exerciseCatalog->all()['exercises'] ?? [])
            ->filter(fn (mixed $exercise): bool => is_array($exercise)
                && in_array($exercise['category'] ?? null, [ExerciseCategory::Mobility->value, ExerciseCategory::Cardio->value], true))
            ->pluck('id')
            ->all();

        return collect($this->trainingHistory->performedExercises())
            ->reject(fn (string $exercise): bool => in_array($exercise, $nonResistanceExercises, true))
            ->sort()
            ->mapWithKeys(function (string $exercise) use ($analysis, $startsNewPhase): array {
                $trend = $analysis->exerciseTrends[$exercise] ?? 'insufficient';
                $lastResult = $this->trainingHistory->resultsForExercise($exercise)->last();
                $isStale = in_array($exercise, $analysis->staleExercises, true);

                return [$exercise => [
                    'status' => $this->status($trend, $lastResult->feedback, $isStale && $startsNewPhase),
                    'trend' => $trend,
                    'lastResult' => $lastResult,
                ]];
            })
            ->all();
    }

    private function status(string $trend, ?ExerciseFeedback $lastFeedback, bool $canRotate): ExerciseStatus
    {
        return match (true) {
            $trend === 'regressing' || $lastFeedback === ExerciseFeedback::VeryHard => ExerciseStatus::Reduce,
            $trend === 'plateau' && $canRotate => ExerciseStatus::Rotate,
            $trend === 'improving' || in_array($lastFeedback, [ExerciseFeedback::Easy, ExerciseFeedback::VeryEasy], true) => ExerciseStatus::Progress,
            default => ExerciseStatus::Keep,
        };
    }
}
