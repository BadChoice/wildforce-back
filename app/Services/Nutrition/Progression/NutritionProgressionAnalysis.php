<?php

namespace App\Services\Nutrition\Progression;

use App\Enums\EnergyDemandLevel;
use App\Enums\NutritionDayType;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use Carbon\CarbonInterface;

final readonly class NutritionProgressionAnalysis
{
    /**
     * @param  list<array{date: CarbonInterface, weekday: string, workoutDay: WorkoutDay|null, energyDemand: EnergyDemandLevel, dayType: NutritionDayType, calorieAdjustment: int, calories: int, protein: int, carbs: int, fat: int}>  $days
     */
    public function __construct(
        public CarbonInterface $startsOn,
        public string $goal,
        public string $bodyCompositionPhase,
        public ?WorkoutPlan $sourceWorkoutPlan,
        public EnergyExpenditureEstimate $energyExpenditure,
        public array $days,
        public bool $wantsMealSuggestions,
    ) {}
}
