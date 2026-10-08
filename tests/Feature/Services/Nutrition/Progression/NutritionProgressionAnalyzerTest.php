<?php

use App\Enums\EnergyDemandLevel;
use App\Enums\NutritionDayType;
use App\Models\NutritionProfile;
use App\Models\TrainingPreference;
use App\Models\User;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use App\Services\Nutrition\Progression\NutritionProgressionAnalyzer;

test('calculates deterministic targets from the user and scheduled workout demand', function () {
    $this->travelTo('2026-10-05 09:00:00');
    $user = User::factory()->create([
        'height' => 180,
        'weight' => 80,
        'birth_date' => '1996-10-05',
        'gender' => 'male',
        'language' => 'en',
    ]);
    TrainingPreference::factory()->for($user)->create([
        'goal' => 'buildMuscle',
        'lifestyle' => 'sedentary',
        'workout_days' => ['monday'],
    ]);
    NutritionProfile::factory()->for($user)->create();
    $workoutPlan = WorkoutPlan::factory()->for($user)->create(['starts_on' => '2026-10-05']);
    WorkoutDay::factory()->for($user)->for($workoutPlan, 'plan')->create(['intended_weekday' => 'monday', 'focus' => 'fullBody']);

    $analysis = (new NutritionProgressionAnalyzer($user))->analyze();

    expect($analysis->goal)->toBe('buildMuscle')
        ->and($analysis->bodyCompositionPhase)->toBe('bulk')
        ->and($analysis->sourceWorkoutPlan?->id)->toBe($workoutPlan->id)
        ->and($analysis->days)->toHaveCount(7)
        ->and($analysis->days[0])->toMatchArray([
            'weekday' => 'monday',
            'dayType' => NutritionDayType::Training,
            'energyDemand' => EnergyDemandLevel::High,
            'calories' => 2606,
            'protein' => 144,
            'carbs' => 364,
            'fat' => 64,
        ]);
});

test('bases targets on Apple Health energy while keeping the formula guardrails', function () {
    $this->travelTo('2026-10-05 09:00:00');
    $user = User::factory()->create([
        'height' => 180,
        'weight' => 80,
        'birth_date' => '1996-10-05',
        'gender' => 'male',
        'language' => 'en',
    ]);
    TrainingPreference::factory()->for($user)->create([
        'goal' => 'buildMuscle',
        'lifestyle' => 'sedentary',
        'workout_days' => ['monday'],
    ]);
    NutritionProfile::factory()->for($user)->create();

    $analysis = (new NutritionProgressionAnalyzer($user, appleHealthEnergyDays(21, 3000)))->analyze();

    expect($analysis->energyExpenditure->source)->toBe('healthKit')
        ->and($analysis->energyExpenditure->averageDailyCalories)->toBe(3000)
        ->and($analysis->energyExpenditure->fallbackCalories)->toBe(2172)
        ->and($analysis->days[1]['calories'])->toBe(2606);
});
