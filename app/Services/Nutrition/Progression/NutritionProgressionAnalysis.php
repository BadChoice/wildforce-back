<?php

namespace App\Services\Nutrition\Progression;

use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use Carbon\CarbonInterface;

final readonly class NutritionProgressionAnalysis
{
    /**
     * @param  list<array{date: CarbonInterface, weekday: string, workoutDay: WorkoutDay|null, energyDemand: string, dayType: string, calorieAdjustment: int, calories: int, protein: int, carbs: int, fat: int}>  $days
     */
    public function __construct(
        public CarbonInterface $startsOn,
        public string $goal,
        public string $bodyCompositionPhase,
        public ?WorkoutPlan $sourceWorkoutPlan,
        public array $days,
        public bool $wantsMealSuggestions,
    ) {}
}
