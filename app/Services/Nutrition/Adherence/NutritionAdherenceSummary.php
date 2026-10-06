<?php

namespace App\Services\Nutrition\Adherence;

use App\Enums\NutritionAdherenceStatus;
use App\Models\NutritionPlan;
use Carbon\CarbonImmutable;

final readonly class NutritionAdherenceSummary
{
    /**
     * @param  list<NutritionDayAdherence>  $days  The complete days of the period, oldest first.
     */
    public function __construct(
        public CarbonImmutable $today,
        public array $days,
        public ?CarbonImmutable $lastLoggedAt,
        public ?NutritionPlan $activePlan,
        public ?NutritionPlan $latestPlan,
    ) {}

    public function periodDays(): int
    {
        return count($this->days);
    }

    public function loggedDays(): int
    {
        return count(array_filter($this->days, fn (NutritionDayAdherence $day): bool => $day->isLogged()));
    }

    public function onTargetDays(): int
    {
        return count(array_filter($this->days, fn (NutritionDayAdherence $day): bool => $day->status === NutritionAdherenceStatus::OnTarget));
    }

    /**
     * Average signed percentage difference from the calorie target across logged, planned days.
     */
    public function calorieDifferencePercentage(): ?int
    {
        $days = $this->comparableDays();

        if ($days === []) {
            return null;
        }

        $logged = array_sum(array_map(fn (NutritionDayAdherence $day): float => $day->calories, $days));
        $target = array_sum(array_map(fn (NutritionDayAdherence $day): float => (float) $day->planDay->target_calories, $days));

        return (int) round((($logged / $target) - 1) * 100);
    }

    /**
     * Average share of the protein target reached across logged, planned days.
     */
    public function proteinPercentage(): ?int
    {
        $days = array_filter($this->comparableDays(), fn (NutritionDayAdherence $day): bool => (float) $day->planDay->target_protein_grams > 0);

        if ($days === []) {
            return null;
        }

        $logged = array_sum(array_map(fn (NutritionDayAdherence $day): float => $day->proteinGrams, $days));
        $target = array_sum(array_map(fn (NutritionDayAdherence $day): float => (float) $day->planDay->target_protein_grams, $days));

        return (int) round(($logged / $target) * 100);
    }

    public function daysSinceLastLog(): ?int
    {
        if ($this->lastLoggedAt === null) {
            return null;
        }

        return (int) $this->lastLoggedAt->startOfDay()->diffInDays($this->today);
    }

    /**
     * @return list<NutritionDayAdherence>
     */
    private function comparableDays(): array
    {
        return array_values(array_filter($this->days, fn (NutritionDayAdherence $day): bool => $day->hasPlan() && $day->isLogged()));
    }
}
