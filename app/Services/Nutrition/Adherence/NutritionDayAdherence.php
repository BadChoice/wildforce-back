<?php

namespace App\Services\Nutrition\Adherence;

use App\Enums\NutritionAdherenceStatus;
use App\Models\NutritionDay;
use Carbon\CarbonImmutable;

final readonly class NutritionDayAdherence
{
    public function __construct(
        public CarbonImmutable $date,
        public ?NutritionDay $planDay,
        public int $entryCount,
        public float $calories,
        public float $proteinGrams,
        public float $carbsGrams,
        public float $fatGrams,
        public NutritionAdherenceStatus $status,
        public bool $isToday,
    ) {}

    public function hasPlan(): bool
    {
        return $this->planDay !== null && (float) $this->planDay->target_calories > 0;
    }

    public function isLogged(): bool
    {
        return $this->entryCount > 0;
    }

    /**
     * Signed percentage difference between logged and target calories.
     */
    public function calorieDifferencePercentage(): ?int
    {
        if (! $this->hasPlan() || ! $this->isLogged()) {
            return null;
        }

        return (int) round((($this->calories / (float) $this->planDay->target_calories) - 1) * 100);
    }

    /**
     * Share of the protein target reached, as a percentage.
     */
    public function proteinPercentage(): ?int
    {
        if (! $this->hasPlan() || ! $this->isLogged() || (float) $this->planDay->target_protein_grams <= 0) {
            return null;
        }

        return (int) round(($this->proteinGrams / (float) $this->planDay->target_protein_grams) * 100);
    }
}
