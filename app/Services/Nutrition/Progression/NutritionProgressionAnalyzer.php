<?php

namespace App\Services\Nutrition\Progression;

use App\Enums\DietaryStyle;
use App\Enums\EnergyDemandLevel;
use App\Enums\NutritionDayType;
use App\Enums\WorkoutFocus;
use App\Models\User;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use Carbon\CarbonInterface;
use RuntimeException;

final class NutritionProgressionAnalyzer
{
    /**
     * @param  list<array{date: string, active_calories: float, basal_calories: float|null}>  $dailyEnergy  Apple Health days reported by the client, if any
     */
    public function __construct(
        private readonly User $user,
        private readonly array $dailyEnergy = [],
    ) {}

    public function analyze(): NutritionProgressionAnalysis
    {
        $this->user->loadMissing([
            'trainingPreferences',
            'nutritionProfile',
            'bodyMetrics',
            'workoutPlans' => fn ($query) => $query->orderByDesc('starts_on')->orderByDesc('created_at')->with('workoutDays'),
        ]);

        if ($this->user->nutritionProfile === null) {
            throw new RuntimeException('Nutrition plan generation requires a nutrition profile.');
        }

        if ($this->user->height === null || $this->user->weight === null || $this->user->birth_date === null || $this->user->gender === null) {
            throw new RuntimeException('Nutrition plan generation requires height, weight, birth date, and gender.');
        }

        $startsOn = now()->startOfDay();
        $goal = $this->user->trainingPreferences?->goal ?? 'generalFitness';
        $phase = $this->bodyCompositionPhase($this->user->trainingPreferences?->body_composition_phase, $goal);
        $sourceWorkoutPlan = $this->user->workoutPlans->first();
        $energyExpenditure = (new EnergyExpenditureEstimator)->estimate($this->maintenanceCalories(), $this->dailyEnergy);
        $days = collect(range(0, 6))->map(function (int $offset) use ($startsOn, $sourceWorkoutPlan, $energyExpenditure, $phase, $goal): array {
            $date = $startsOn->copy()->addDays($offset);
            $workoutDay = $this->workoutDayFor($date, $sourceWorkoutPlan);
            $demand = $this->workoutDemand($workoutDay);

            return [
                'date' => $date,
                'weekday' => strtolower($date->englishDayOfWeek),
                'workoutDay' => $workoutDay,
                ...$demand,
                ...$this->macros($energyExpenditure, $phase, $goal, $demand),
            ];
        })->all();

        return new NutritionProgressionAnalysis(
            startsOn: $startsOn,
            goal: $goal,
            bodyCompositionPhase: $phase,
            sourceWorkoutPlan: $sourceWorkoutPlan,
            energyExpenditure: $energyExpenditure,
            days: $days,
            wantsMealSuggestions: $this->user->nutritionProfile->wants_meal_suggestions,
        );
    }

    /** @return array{energyDemand: EnergyDemandLevel, dayType: NutritionDayType, calorieAdjustment: int} */
    private function workoutDemand(?WorkoutDay $workoutDay): array
    {
        if ($workoutDay === null) {
            return ['energyDemand' => EnergyDemandLevel::Low, 'dayType' => NutritionDayType::Rest, 'calorieAdjustment' => -150];
        }

        $duration = $workoutDay->active_duration_seconds === null ? ($workoutDay->estimated_duration_minutes ?? 45) : max(1, (int) round($workoutDay->active_duration_seconds / 60));

        return match ($workoutDay->focus) {
            WorkoutFocus::Mobility, WorkoutFocus::Recovery => ['energyDemand' => EnergyDemandLevel::Low, 'dayType' => NutritionDayType::Recovery, 'calorieAdjustment' => -100],
            WorkoutFocus::Core => ['energyDemand' => EnergyDemandLevel::Low, 'dayType' => NutritionDayType::Training, 'calorieAdjustment' => -50],
            WorkoutFocus::Legs, WorkoutFocus::FullBody => ['energyDemand' => EnergyDemandLevel::High, 'dayType' => NutritionDayType::Training, 'calorieAdjustment' => 250],
            WorkoutFocus::LowerBody => $duration >= 50 ? ['energyDemand' => EnergyDemandLevel::High, 'dayType' => NutritionDayType::Training, 'calorieAdjustment' => 250] : ['energyDemand' => EnergyDemandLevel::Medium, 'dayType' => NutritionDayType::Training, 'calorieAdjustment' => 75],
            WorkoutFocus::Cardio => $duration >= 45 ? ['energyDemand' => EnergyDemandLevel::High, 'dayType' => NutritionDayType::Training, 'calorieAdjustment' => 225] : ['energyDemand' => EnergyDemandLevel::Medium, 'dayType' => NutritionDayType::Training, 'calorieAdjustment' => 100],
            default => $duration >= 75 ? ['energyDemand' => EnergyDemandLevel::High, 'dayType' => NutritionDayType::Training, 'calorieAdjustment' => 175] : ['energyDemand' => EnergyDemandLevel::Medium, 'dayType' => NutritionDayType::Training, 'calorieAdjustment' => 75],
        };
    }

    /**
     * @param  array{energyDemand: EnergyDemandLevel, dayType: NutritionDayType, calorieAdjustment: int}  $demand
     * @return array{calories: int, protein: int, carbs: int, fat: int}
     */
    private function macros(EnergyExpenditureEstimate $energyExpenditure, string $phase, string $goal, array $demand): array
    {
        $weight = (float) $this->user->weight;
        $calories = $energyExpenditure->averageDailyCalories + $this->bodyCompositionAdjustment($phase, $goal) + $demand['calorieAdjustment'];
        $bmr = $this->bmr();
        $fallbackCalories = $energyExpenditure->fallbackCalories;
        $minimum = max($this->user->gender === 'male' ? 1500 : 1200, (int) round($bmr * 1.10), (int) round($fallbackCalories * 0.75));
        $maximum = max($minimum + 200, (int) round($fallbackCalories * 1.20));
        $calories = min(max($calories, $minimum), $maximum);
        $protein = max(110, (int) round($weight * (($phase === 'cut' || in_array($goal, ['loseWeight', 'bodyRecomposition'], true)) ? 2.2 : ($goal === 'improveEndurance' ? 1.7 : 1.8))));
        $fat = (int) round($weight * ($this->user->nutritionProfile?->dietary_style === DietaryStyle::Vegan ? 0.85 : 0.80));
        $floor = (int) round($weight * $this->carbFloorMultiplier($demand['energyDemand'], $phase));
        $calories = max($calories, ($protein * 4) + ($fat * 9) + ($floor * 4));
        $carbs = (int) round(max($calories - ($protein * 4) - ($fat * 9), $floor * 4) / 4);

        return compact('calories', 'protein', 'carbs', 'fat');
    }

    private function maintenanceCalories(): int
    {
        $multiplier = match ($this->user->trainingPreferences?->lifestyle) {
            'lightlyActive' => 1.375,
            'moderatelyActive' => 1.55,
            'veryActive' => 1.725,
            default => 1.20,
        };
        $workoutDayAdjustment = min(count($this->user->trainingPreferences?->workout_days ?? []) * 0.02, 0.12);

        return (int) round($this->bmr() * ($multiplier + $workoutDayAdjustment));
    }

    private function bmr(): float
    {
        return (10 * (float) $this->user->weight) + (6.25 * $this->user->height) - (5 * $this->user->birth_date->age) + ($this->user->gender === 'male' ? 5 : -161);
    }

    private function bodyCompositionPhase(?string $phase, string $goal): string
    {
        if (in_array($phase, ['bulk', 'cut', 'maintain'], true)) {
            return $phase;
        }

        return match ($goal) {
            'loseWeight' => 'cut',
            'buildMuscle' => 'bulk',
            default => 'maintain',
        };
    }

    private function bodyCompositionAdjustment(string $phase, string $goal): int
    {
        return match ($phase) {
            'bulk' => 250,
            'cut' => -350,
            'maintain' => $goal === 'bodyRecomposition' ? -125 : 0,
        };
    }

    private function carbFloorMultiplier(EnergyDemandLevel $demand, string $phase): float
    {
        $base = match ($demand) {
            EnergyDemandLevel::Medium => 2.25,
            EnergyDemandLevel::High => 3.25,
            default => 1.5,
        };

        return match ($phase) {
            'bulk' => $base + 0.25,
            'cut' => max(1.3, $base - 0.15),
            default => $base,
        };
    }

    private function workoutDayFor(CarbonInterface $date, ?WorkoutPlan $plan): ?WorkoutDay
    {
        if ($plan === null) {
            return null;
        }

        return $plan->workoutDays->first(fn (WorkoutDay $day): bool => $day->scheduled_for?->isSameDay($date) ?? false)
            ?? $plan->workoutDays->first(fn (WorkoutDay $day): bool => $day->intended_weekday === strtolower($date->englishDayOfWeek));
    }
}
