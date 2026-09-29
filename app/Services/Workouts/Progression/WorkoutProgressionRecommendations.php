<?php

namespace App\Services\Workouts\Progression;

final class WorkoutProgressionRecommendations
{
    public function __construct(private readonly WorkoutProgressionAnalyzer $analyzer) {}

    /**
     * @param  array<string, int>  $feedbackBreakdown
     * @param  array{neglected: list<string>, overworked: list<string>}  $muscleGroupBalance
     * @return list<array{title: string, description: string, tone: string}>
     */
    public function all(float $completionRate, array $feedbackBreakdown, array $muscleGroupBalance): array
    {
        $recommendations = [];
        $hardFeedback = ($feedbackBreakdown['hard'] ?? 0) + ($feedbackBreakdown['veryHard'] ?? 0);
        $hardFeedbackRatio = array_sum($feedbackBreakdown) === 0
            ? 0.0
            : $hardFeedback / array_sum($feedbackBreakdown);

        if ($completionRate < 0.5 || $hardFeedbackRatio > 0.6) {
            $recommendations[] = [
                'title' => 'Review training load',
                'description' => 'Recent adherence or perceived difficulty suggests keeping the next block manageable.',
                'tone' => 'amber',
            ];
        }

        $plateauedExercises = array_keys(array_filter(
            $this->analyzer->exerciseTrends(),
            fn (string $trend): bool => $trend === 'plateau',
        ));

        if ($plateauedExercises !== []) {
            $recommendations[] = [
                'title' => 'Refresh '.(string) str($plateauedExercises[0])->headline(),
                'description' => 'This exercise has plateaued; consider changing its variation, rep range, or progression approach.',
                'tone' => 'indigo',
            ];
        }

        if ($muscleGroupBalance['neglected'] !== []) {
            $recommendations[] = [
                'title' => 'Prioritise '.(string) str($muscleGroupBalance['neglected'][0])->headline(),
                'description' => 'This muscle group is receiving less work than the client’s recent training balance.',
                'tone' => 'emerald',
            ];
        }

        if ($recommendations === []) {
            $recommendations[] = [
                'title' => 'Continue progressive overload',
                'description' => 'Recent training signals do not point to a specific adjustment for the next block.',
                'tone' => 'green',
            ];
        }

        return array_slice($recommendations, 0, 3);
    }
}
