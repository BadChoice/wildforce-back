<?php

namespace App\Services\Workouts\Progression;

final readonly class ProgressionAnalysis
{
    /**
     * @param  array<string, string>  $exerciseTrends
     * @param  list<string>  $neglectedMuscleGroups
     * @param  list<string>  $overworkedMuscleGroups
     * @param  list<string>  $staleExercises
     * @param  array<string, int>  $recentFeedbackBreakdown
     */
    public function __construct(
        public int $mesocycleNumber,
        public array $exerciseTrends,
        public string $overallVolumeTrend,
        public array $neglectedMuscleGroups,
        public array $overworkedMuscleGroups,
        public array $staleExercises,
        public float $completionRate,
        public int $recentCompletedWorkouts,
        public string $readinessLevel,
        public array $recentFeedbackBreakdown,
    ) {}
}
