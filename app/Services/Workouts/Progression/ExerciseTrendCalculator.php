<?php

namespace App\Services\Workouts\Progression;

use App\Enums\ExerciseFeedback;
use App\Models\ExerciseResult;
use Illuminate\Support\Collection;

/**
 * Classifies an exercise's progression from its session results.
 *
 * Each session is reduced to its best set, scored by estimated one-rep max (Epley) for loaded
 * work or by reps for bodyweight work. Reported effort is added as reps in reserve, so the same
 * load felt as `hard` scores lower than when it felt `easy`.
 */
final class ExerciseTrendCalculator
{
    private const int WindowSize = 3;

    private const float LoadChangeThreshold = 0.025;

    private const float RepsChangeThreshold = 1.0;

    private const float SpikeThreshold = 0.4;

    /**
     * @param  Collection<int, ExerciseResult>  $results  ordered from oldest to newest
     */
    public function trend(Collection $results): string
    {
        $performances = $results
            ->map(fn (ExerciseResult $result): ?array => $this->performance($result))
            ->filter()
            ->values();

        if ($performances->isEmpty()) {
            return 'insufficient';
        }

        $kind = $performances->last()['kind'];
        $scores = $this->withoutSpikes($performances->where('kind', $kind)->pluck('score')->values());

        if ($scores->count() < 2) {
            return 'insufficient';
        }

        $recentCount = min(self::WindowSize, intdiv($scores->count() + 1, 2));
        $recentAverage = (float) $scores->slice(-$recentCount)->average();
        $earlierAverage = (float) $scores->slice(0, -$recentCount)->slice(-self::WindowSize)->average();
        $change = $kind === 'load'
            ? ($recentAverage - $earlierAverage) / $earlierAverage
            : $recentAverage - $earlierAverage;
        $threshold = $kind === 'load' ? self::LoadChangeThreshold : self::RepsChangeThreshold;

        return match (true) {
            $change >= $threshold => 'improving',
            $change <= -$threshold => 'regressing',
            default => 'plateau',
        };
    }

    /**
     * The session's best set: the highest estimated one-rep max for loaded work, otherwise the most reps.
     *
     * @return array{weight: float, reps: int}|null
     */
    public function bestSet(ExerciseResult $result): ?array
    {
        $sets = $this->sets($result);
        $loadedSets = $sets->filter(fn (array $set): bool => $set['weight'] > 0 && $set['reps'] > 0);

        if ($loadedSets->isNotEmpty()) {
            return $loadedSets->sortByDesc(fn (array $set): float => $this->estimatedOneRepMax($set['weight'], $set['reps']))->first();
        }

        $bestSet = $sets->sortByDesc('reps')->first();

        return $bestSet !== null && $bestSet['reps'] > 0 ? $bestSet : null;
    }

    /**
     * @return array{kind: 'load'|'reps', score: float}|null
     */
    private function performance(ExerciseResult $result): ?array
    {
        $bestSet = $this->bestSet($result);

        if ($bestSet === null) {
            return null;
        }

        $reserve = $this->repsInReserve($result->feedback);

        return $bestSet['weight'] > 0
            ? ['kind' => 'load', 'score' => $this->estimatedOneRepMax($bestSet['weight'], $bestSet['reps'] + $reserve)]
            : ['kind' => 'reps', 'score' => (float) ($bestSet['reps'] + $reserve)];
    }

    private function estimatedOneRepMax(float $weight, int $reps): float
    {
        return $weight * (1 + $reps / 30);
    }

    /**
     * @return Collection<int, array{weight: float, reps: int}>
     */
    private function sets(ExerciseResult $result): Collection
    {
        $perSetReps = $result->per_set_reps ?? [];

        if ($perSetReps === []) {
            return collect([['weight' => (float) $result->completed_weight, 'reps' => (int) $result->completed_reps]]);
        }

        $perSetWeights = $result->per_set_weights_kg ?? [];

        return collect($perSetReps)->values()->map(fn (mixed $reps, int $index): array => [
            'weight' => (float) ($perSetWeights[$index] ?? $result->completed_weight),
            'reps' => (int) $reps,
        ]);
    }

    private function repsInReserve(?ExerciseFeedback $feedback): int
    {
        return match ($feedback) {
            ExerciseFeedback::VeryEasy => 4,
            ExerciseFeedback::Easy => 3,
            ExerciseFeedback::Hard => 1,
            ExerciseFeedback::VeryHard => 0,
            default => 2,
        };
    }

    /**
     * Drop isolated values far from both neighbours in the same direction, such as a mistyped load.
     *
     * @param  Collection<int, float>  $scores
     * @return Collection<int, float>
     */
    private function withoutSpikes(Collection $scores): Collection
    {
        return $scores
            ->reject(function (float $score, int $index) use ($scores): bool {
                $previous = $scores->get($index - 1);
                $next = $scores->get($index + 1);

                if ($previous === null || $next === null) {
                    return false;
                }

                $previousChange = ($score - $previous) / $previous;
                $nextChange = ($score - $next) / $next;

                return abs($previousChange) > self::SpikeThreshold
                    && abs($nextChange) > self::SpikeThreshold
                    && ($previousChange > 0) === ($nextChange > 0);
            })
            ->values();
    }
}
