<?php

namespace App\Services\Nutrition\Progression;

final readonly class EnergyExpenditureEstimate
{
    /**
     * @param  'healthKit'|'blended'|'fallback'  $source
     */
    public function __construct(
        public int $averageDailyCalories,
        public int $fallbackCalories,
        public string $source,
        public int $observedDays,
        public int $consideredDays,
    ) {}
}
