<?php

use App\Enums\NutritionAdherenceStatus;
use App\Models\NutritionDay;
use App\Models\NutritionLogEntry;
use App\Models\NutritionLogItem;
use App\Models\NutritionPlan;
use App\Models\User;
use App\Services\Nutrition\NutritionAdherenceCalculator;
use Carbon\CarbonImmutable;

test('it classifies a logged day against the plan targets', function (float $calories, float $protein, NutritionAdherenceStatus $expectedStatus) {
    $this->travelTo('2026-10-06 12:00:00');
    $user = User::factory()->create();
    nutritionPlanDay($user, '2026-10-05', calories: 2000, protein: 150);
    loggedFood($user, '2026-10-05 13:00:00', calories: $calories, protein: $protein);

    [$day] = app(NutritionAdherenceCalculator::class)->days($user, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-05'));

    expect($day->status)->toBe($expectedStatus);
})->with([
    'on target' => [2100, 140, NutritionAdherenceStatus::OnTarget],
    'on target at the tolerance limits' => [2200, 135, NutritionAdherenceStatus::OnTarget],
    'low protein' => [2000, 120, NutritionAdherenceStatus::LowProtein],
    'under the calorie target' => [1700, 150, NutritionAdherenceStatus::Under],
    'over the calorie target' => [2300, 150, NutritionAdherenceStatus::Over],
]);

test('it classifies days that are unplanned, unlogged, in progress or upcoming', function () {
    $this->travelTo('2026-10-06 12:00:00');
    $user = User::factory()->create();
    nutritionPlanDay($user, '2026-10-05', calories: 2000, protein: 150);
    nutritionPlanDay($user, '2026-10-06', calories: 2000, protein: 150);
    nutritionPlanDay($user, '2026-10-07', calories: 2000, protein: 150);
    loggedFood($user, '2026-10-04 09:00:00', calories: 800, protein: 40);
    loggedFood($user, '2026-10-06 09:00:00', calories: 600, protein: 40);

    $days = app(NutritionAdherenceCalculator::class)->days($user, CarbonImmutable::parse('2026-10-04'), CarbonImmutable::parse('2026-10-07'));

    expect(array_map(fn ($day) => $day->status, $days))->toBe([
        NutritionAdherenceStatus::NoPlan,
        NutritionAdherenceStatus::NotLogged,
        NutritionAdherenceStatus::InProgress,
        NutritionAdherenceStatus::Upcoming,
    ])
        ->and($days[0]->calories)->toBe(800.0)
        ->and($days[2]->isToday)->toBeTrue();
});

test('it groups logged food by the calendar day in the user timezone', function (?string $timezone, string $expectedDate) {
    $this->travelTo('2026-10-06 12:00:00');
    $user = User::factory()->create(['timezone' => $timezone]);
    loggedFood($user, '2026-10-04 23:30:00', calories: 500, protein: 30);

    $days = app(NutritionAdherenceCalculator::class)->days($user, CarbonImmutable::parse('2026-10-04'), CarbonImmutable::parse('2026-10-05'));
    $loggedDates = collect($days)->filter->isLogged()->map(fn ($day) => $day->date->toDateString())->values()->all();

    expect($loggedDates)->toBe([$expectedDate]);
})->with([
    'ahead of UTC' => ['Europe/Madrid', '2026-10-05'],
    'missing timezone falls back to the app timezone' => [null, '2026-10-04'],
    'invalid timezone falls back to the app timezone' => ['Mars/Olympus', '2026-10-04'],
]);

test('it uses the most recently created plan when plans overlap unless a plan is given', function () {
    $this->travelTo('2026-10-06 12:00:00');
    $user = User::factory()->create();
    $olderPlanDay = nutritionPlanDay($user, '2026-10-05', calories: 2000, protein: 150);
    $this->travelTo('2026-10-06 13:00:00');
    nutritionPlanDay($user, '2026-10-05', calories: 3000, protein: 150);
    $calculator = app(NutritionAdherenceCalculator::class);
    $date = CarbonImmutable::parse('2026-10-05');

    [$effectiveDay] = $calculator->days($user, $date, $date);
    [$olderPlanScopedDay] = $calculator->days($user, $date, $date, $olderPlanDay->plan);

    expect((float) $effectiveDay->planDay->target_calories)->toBe(3000.0)
        ->and($olderPlanScopedDay->planDay->id)->toBe($olderPlanDay->id);
});

test('it summarises the complete days before today', function () {
    $this->travelTo('2026-10-06 12:00:00');
    $user = User::factory()->create();
    $plan = NutritionPlan::factory()->for($user)->create(['starts_on' => '2026-10-01']);
    nutritionPlanDay($user, '2026-10-04', calories: 2000, protein: 150, plan: $plan);
    nutritionPlanDay($user, '2026-10-05', calories: 2000, protein: 150, plan: $plan);
    loggedFood($user, '2026-10-04 13:00:00', calories: 2200, protein: 120);
    loggedFood($user, '2026-10-05 13:00:00', calories: 2000, protein: 150);
    loggedFood($user, '2026-10-06 08:00:00', calories: 400, protein: 30);

    $summary = app(NutritionAdherenceCalculator::class)->summary($user);

    expect($summary->periodDays())->toBe(7)
        ->and($summary->days[0]->date->toDateString())->toBe('2026-09-29')
        ->and($summary->days[6]->date->toDateString())->toBe('2026-10-05')
        ->and($summary->loggedDays())->toBe(2)
        ->and($summary->onTargetDays())->toBe(1)
        ->and($summary->calorieDifferencePercentage())->toBe(5)
        ->and($summary->proteinPercentage())->toBe(90)
        ->and($summary->daysSinceLastLog())->toBe(0)
        ->and($summary->activePlan?->id)->toBe($plan->id);
});

test('it reports the latest plan when no plan covers today', function () {
    $this->travelTo('2026-10-20 12:00:00');
    $user = User::factory()->create();
    $plan = NutritionPlan::factory()->for($user)->create(['starts_on' => '2026-10-01']);

    $summary = app(NutritionAdherenceCalculator::class)->summary($user);

    expect($summary->activePlan)->toBeNull()
        ->and($summary->latestPlan?->id)->toBe($plan->id)
        ->and($summary->daysSinceLastLog())->toBeNull();
});

test('it counts recently logged days and the last log for several users', function () {
    $this->travelTo('2026-10-06 12:00:00');
    $activeUser = User::factory()->create();
    $inactiveUser = User::factory()->create();
    $newUser = User::factory()->create();
    loggedFood($activeUser, '2026-10-05 08:00:00', calories: 400, protein: 30);
    loggedFood($activeUser, '2026-10-05 20:00:00', calories: 700, protein: 40);
    loggedFood($activeUser, '2026-10-01 13:00:00', calories: 700, protein: 40);
    loggedFood($activeUser, '2026-10-06 08:00:00', calories: 400, protein: 30);
    loggedFood($inactiveUser, '2026-09-20 13:00:00', calories: 700, protein: 40);

    $logging = app(NutritionAdherenceCalculator::class)->recentLogging([$activeUser, $inactiveUser, $newUser]);

    expect($logging[$activeUser->id]['loggedDays'])->toBe(2)
        ->and($logging[$activeUser->id]['lastLoggedAt']->toDateTimeString())->toBe('2026-10-06 08:00:00')
        ->and($logging[$inactiveUser->id]['loggedDays'])->toBe(0)
        ->and($logging[$inactiveUser->id]['lastLoggedAt']->toDateString())->toBe('2026-09-20')
        ->and($logging[$newUser->id]['lastLoggedAt'])->toBeNull();
});

function nutritionPlanDay(User $user, string $date, float $calories, float $protein, ?NutritionPlan $plan = null): NutritionDay
{
    return NutritionDay::factory()
        ->for($plan ?? NutritionPlan::factory()->for($user)->create(['starts_on' => $date]), 'plan')
        ->create([
            'date' => $date,
            'target_calories' => $calories,
            'target_protein_grams' => $protein,
        ]);
}

function loggedFood(User $user, string $loggedAt, float $calories, float $protein): NutritionLogEntry
{
    $entry = NutritionLogEntry::factory()->for($user)->create(['logged_at' => $loggedAt]);
    NutritionLogItem::factory()->for($entry, 'entry')->create([
        'calories' => $calories,
        'protein_grams' => $protein,
        'carbs_grams' => 0,
        'fat_grams' => 0,
    ]);

    return $entry;
}
