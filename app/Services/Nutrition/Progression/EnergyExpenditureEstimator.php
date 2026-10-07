<?php

namespace App\Services\Nutrition\Progression;

use Illuminate\Support\Collection;

/**
 * Estimates daily maintenance calories from Apple Health energy data reported
 * by the client, falling back to the formula estimate when coverage is low.
 */
final class EnergyExpenditureEstimator
{
    private const int LookbackDays = 21;

    private const float MinimumHealthKitCoverage = 0.70;

    private const int MinimumHealthKitDays = 10;

    private const float MinimumBlendCoverage = 0.40;

    private const int MinimumBlendDays = 5;

    /**
     * @param  list<array{date: string, active_calories: float, basal_calories: float|null}>  $dailyEnergy
     */
    public function estimate(int $fallbackCalories, array $dailyEnergy): EnergyExpenditureEstimate
    {
        $observedTotals = collect($dailyEnergy)
            ->sortByDesc('date')
            ->take(self::LookbackDays)
            ->filter(fn (array $day): bool => $day['basal_calories'] !== null)
            ->map(fn (array $day): float => $day['active_calories'] + $day['basal_calories'])
            ->filter(fn (float $total): bool => $total > 0)
            ->sort()
            ->values();
        $observedDays = $observedTotals->count();

        if ($observedDays === 0) {
            return $this->estimateFrom($fallbackCalories, $fallbackCalories, 'fallback', 0);
        }

        $coverage = $observedDays / self::LookbackDays;
        $healthKitAverage = (int) round($this->trimmed($observedTotals)->avg());

        if ($coverage >= self::MinimumHealthKitCoverage && $observedDays >= self::MinimumHealthKitDays) {
            return $this->estimateFrom($healthKitAverage, $fallbackCalories, 'healthKit', $observedDays);
        }

        if ($coverage >= self::MinimumBlendCoverage && $observedDays >= self::MinimumBlendDays) {
            $healthKitWeight = min(max($coverage, 0.35), 0.65);
            $blendedAverage = (int) round(($healthKitAverage * $healthKitWeight) + ($fallbackCalories * (1 - $healthKitWeight)));

            return $this->estimateFrom($blendedAverage, $fallbackCalories, 'blended', $observedDays);
        }

        return $this->estimateFrom($fallbackCalories, $fallbackCalories, 'fallback', $observedDays);
    }

    /**
     * Drop the lowest and highest tenth of the sorted totals once there is a week of data.
     *
     * @param  Collection<int, float>  $sortedTotals
     * @return Collection<int, float>
     */
    private function trimmed(Collection $sortedTotals): Collection
    {
        $count = $sortedTotals->count();

        if ($count < 7) {
            return $sortedTotals;
        }

        $trimCount = max(1, intdiv($count, 10));

        return $sortedTotals->slice($trimCount, $count - (2 * $trimCount));
    }

    /**
     * @param  'healthKit'|'blended'|'fallback'  $source
     */
    private function estimateFrom(int $averageDailyCalories, int $fallbackCalories, string $source, int $observedDays): EnergyExpenditureEstimate
    {
        return new EnergyExpenditureEstimate(
            averageDailyCalories: $averageDailyCalories,
            fallbackCalories: $fallbackCalories,
            source: $source,
            observedDays: $observedDays,
            consideredDays: self::LookbackDays,
        );
    }
}
