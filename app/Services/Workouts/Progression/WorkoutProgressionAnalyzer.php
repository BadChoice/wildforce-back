<?php

namespace App\Services\Workouts\Progression;

use App\Enums\MesocyclePhase;
use App\Models\ExerciseResult;
use App\Models\WorkoutPlan;
use App\Services\ExerciseCatalog\ExerciseCatalog;
use Illuminate\Support\Collection;

final class WorkoutProgressionAnalyzer
{
    private const int HistoryLimit = TrainingHistory::RecentPlanLimit;

    public function __construct(
        private readonly TrainingHistory $trainingHistory,
        private readonly ExerciseCatalog $exerciseCatalog,
    ) {}

    public function analyze(): ProgressionAnalysis
    {
        $recentPlans = $this->trainingHistory->recentPlans();
        $recentActivePlans = $recentPlans->reject(fn (WorkoutPlan $plan) => $plan->phase === MesocyclePhase::Deload)->values();
        $feedbackBreakdown = $this->recentFeedback($recentPlans);
        $completionRate = $this->completionRate($recentPlans);
        $muscleGroupBalance = $this->muscleGroupBalance($recentPlans);

        return new ProgressionAnalysis(
            mesocycleNumber: $this->trainingHistory->nextMesocycleNumber(),
            exerciseTrends: $this->exerciseTrends(),
            overallVolumeTrend: $this->volumeTrend($recentActivePlans),
            neglectedMuscleGroups: $muscleGroupBalance['neglected'],
            overworkedMuscleGroups: $muscleGroupBalance['overworked'],
            staleExercises: $this->staleExercises($recentActivePlans),
            completionRate: $completionRate,
            recentCompletedWorkouts: $recentPlans
                ->flatMap(fn (WorkoutPlan $plan) => $plan->workoutDays)
                ->where('status', 'completed')
                ->count(),
            readinessLevel: $this->readinessLevel(
                $this->trainingHistory->currentStreak(),
                $completionRate,
                $feedbackBreakdown,
            ),
            recentFeedbackBreakdown: $feedbackBreakdown,
            recentMesocycles: $this->recentMesocycles($recentPlans),
            recommendations: (new WorkoutProgressionRecommendations($this))->all(
                $completionRate,
                $feedbackBreakdown,
                $muscleGroupBalance,
            ),
        );
    }

    /**
     * @param  Collection<int, WorkoutPlan>  $plans
     * @return list<array{number: int, phase: ?string, completionRate: float, completedWorkouts: int}>
     */
    private function recentMesocycles(Collection $plans): array
    {
        return $plans
            ->values()
            ->map(fn (WorkoutPlan $plan, int $index): array => [
                'number' => $plan->mesocycle_number ?? $index + 1,
                'phase' => $plan->phase?->value,
                'completionRate' => $this->completionRate(collect([$plan])),
                'completedWorkouts' => $plan->workoutDays->where('status', 'completed')->count(),
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function exerciseTrends(): array
    {
        $trendCalculator = new ExerciseTrendCalculator;

        return $this->trainingHistory->exerciseProfiles()
            ->pluck('exercise')
            ->merge($this->trainingHistory->performedExercises())
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $exercise): array => [
                $exercise => $trendCalculator->trend($this->trainingHistory->resultsForExercise($exercise)),
            ])
            ->all();
    }

    /**
     * @param  Collection<int, WorkoutPlan>  $plans
     */
    private function volumeTrend(Collection $plans): string
    {
        if ($plans->count() < 2) {
            return 'insufficient';
        }

        $volumes = $plans->map(fn (WorkoutPlan $plan) => $this->planVolume($plan));
        $earlierVolumes = $volumes->slice(0, -1);
        $earlierAverage = $earlierVolumes->average();

        if ($earlierAverage === null || $earlierAverage <= 0) {
            return 'insufficient';
        }

        $change = (($volumes->last() ?? 0) - $earlierAverage) / $earlierAverage;

        return match (true) {
            $change > 0.05 => 'increasing',
            $change < -0.05 => 'decreasing',
            default => 'stable',
        };
    }

    private function planVolume(WorkoutPlan $plan): float
    {
        return (float) $plan->workoutDays
            ->flatMap(fn ($workoutDay) => $workoutDay->exercises)
            ->map(function ($plannedExercise): float {
                $result = $plannedExercise->exerciseResults->last();

                if ($result === null) {
                    return 0.0;
                }

                return (float) ($result->completed_sets ?? 1)
                    * (float) ($result->completed_reps ?? 0)
                    * (float) ($result->completed_weight ?? 1);
            })
            ->sum();
    }

    /**
     * @param  Collection<int, WorkoutPlan>  $plans
     * @return array{neglected: list<string>, overworked: list<string>}
     */
    private function muscleGroupBalance(Collection $plans): array
    {
        $counts = [];
        $metadataByExercise = collect($this->exerciseCatalog->all()['exercises'] ?? [])
            ->keyBy('id');

        foreach ($plans->flatMap(fn (WorkoutPlan $plan) => $plan->workoutDays)->flatMap(fn ($workoutDay) => $workoutDay->exercises) as $plannedExercise) {
            foreach ($metadataByExercise->get($plannedExercise->exercise)['primaryMuscles'] ?? [] as $muscleGroup) {
                if ($muscleGroup !== 'cardio' && $muscleGroup !== 'fullBody') {
                    $counts[$muscleGroup] = ($counts[$muscleGroup] ?? 0) + 1;
                }
            }
        }

        if ($counts === []) {
            return ['neglected' => [], 'overworked' => []];
        }

        $average = array_sum($counts) / count($counts);
        $neglected = array_keys(array_filter($counts, fn (int $count) => $count < $average * 0.5));
        $overworked = array_keys(array_filter($counts, fn (int $count) => $count > $average * 2));
        sort($neglected);
        sort($overworked);

        return ['neglected' => $neglected, 'overworked' => $overworked];
    }

    /**
     * @param  Collection<int, WorkoutPlan>  $plans
     * @return list<string>
     */
    private function staleExercises(Collection $plans): array
    {
        if ($plans->count() < self::HistoryLimit) {
            return [];
        }

        $exerciseSets = $plans
            ->map(fn (WorkoutPlan $plan) => $plan->workoutDays->flatMap(fn ($workoutDay) => $workoutDay->exercises)->pluck('exercise')->unique()->all())
            ->all();
        $staleExercises = array_shift($exerciseSets) ?? [];

        foreach ($exerciseSets as $exerciseSet) {
            $staleExercises = array_values(array_intersect($staleExercises, $exerciseSet));
        }

        sort($staleExercises);

        return $staleExercises;
    }

    /**
     * @param  Collection<int, WorkoutPlan>  $plans
     */
    private function completionRate(Collection $plans): float
    {
        $eligibleDays = $plans
            ->flatMap(fn (WorkoutPlan $plan) => $plan->workoutDays)
            ->reject(fn ($workoutDay) => in_array($workoutDay->status, ['planned', 'draft'], true));

        if ($eligibleDays->isEmpty()) {
            return 1.0;
        }

        return $eligibleDays->where('status', 'completed')->count() / $eligibleDays->count();
    }

    /**
     * @param  Collection<int, WorkoutPlan>  $plans
     * @return array<string, int>
     */
    private function recentFeedback(Collection $plans): array
    {
        return $plans
            ->flatMap(fn (WorkoutPlan $plan) => $plan->workoutDays)
            ->flatMap(fn ($workoutDay) => $workoutDay->exercises)
            ->flatMap(fn ($plannedExercise) => $plannedExercise->exerciseResults)
            ->countBy(fn (ExerciseResult $result): string => $result->feedback->value)
            ->map(fn (int $count) => $count)
            ->all();
    }

    /**
     * @param  array<string, int>  $feedbackBreakdown
     */
    private function readinessLevel(int $streak, float $completionRate, array $feedbackBreakdown): string
    {
        $totalFeedback = array_sum($feedbackBreakdown);
        $hardFeedback = ($feedbackBreakdown['hard'] ?? 0) + ($feedbackBreakdown['veryHard'] ?? 0);
        $hardRatio = $totalFeedback === 0 ? 0.0 : $hardFeedback / $totalFeedback;

        if ($completionRate < 0.5 || $hardRatio > 0.6) {
            return 'low';
        }

        if ($streak >= 5 && $completionRate >= 0.8 && $hardRatio < 0.3) {
            return 'high';
        }

        return 'medium';
    }
}
