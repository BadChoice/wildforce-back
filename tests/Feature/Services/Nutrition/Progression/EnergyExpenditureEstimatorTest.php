<?php

use App\Services\Nutrition\Progression\EnergyExpenditureEstimator;

test('uses the formula estimate when no Apple Health days are reported', function () {
    $estimate = (new EnergyExpenditureEstimator)->estimate(2000, []);

    expect($estimate->averageDailyCalories)->toBe(2000)
        ->and($estimate->fallbackCalories)->toBe(2000)
        ->and($estimate->source)->toBe('fallback')
        ->and($estimate->observedDays)->toBe(0)
        ->and($estimate->consideredDays)->toBe(21);
});

test('uses the Apple Health average when at least ten days cover seventy percent of the window', function () {
    $estimate = (new EnergyExpenditureEstimator)->estimate(2000, appleHealthEnergyDays(15, 2400));

    expect($estimate->averageDailyCalories)->toBe(2400)
        ->and($estimate->fallbackCalories)->toBe(2000)
        ->and($estimate->source)->toBe('healthKit')
        ->and($estimate->observedDays)->toBe(15);
});

test('trims the lowest and highest tenth of the days before averaging', function () {
    $dailyEnergy = [
        ...appleHealthEnergyDays(18, 2400),
        ...appleHealthEnergyDays(1, 900, startOffset: 18),
        ...appleHealthEnergyDays(1, 5000, startOffset: 19),
    ];

    $estimate = (new EnergyExpenditureEstimator)->estimate(2000, $dailyEnergy);

    expect($estimate->averageDailyCalories)->toBe(2400)
        ->and($estimate->observedDays)->toBe(20);
});

test('blends Apple Health with the formula estimate when coverage is partial', function () {
    $estimate = (new EnergyExpenditureEstimator)->estimate(2000, appleHealthEnergyDays(9, 2600));

    expect($estimate->averageDailyCalories)->toBe(2257)
        ->and($estimate->source)->toBe('blended')
        ->and($estimate->observedDays)->toBe(9);
});

test('falls back to the formula estimate when coverage is below forty percent', function () {
    $estimate = (new EnergyExpenditureEstimator)->estimate(2000, appleHealthEnergyDays(8, 2600));

    expect($estimate->averageDailyCalories)->toBe(2000)
        ->and($estimate->source)->toBe('fallback')
        ->and($estimate->observedDays)->toBe(8);
});

test('ignores days without basal energy or with no energy at all', function () {
    $dailyEnergy = [
        ...appleHealthEnergyDays(15, 2400),
        ['date' => '2026-09-19', 'active_calories' => 600.0, 'basal_calories' => null],
        ['date' => '2026-09-18', 'active_calories' => 0.0, 'basal_calories' => 0.0],
    ];

    $estimate = (new EnergyExpenditureEstimator)->estimate(2000, $dailyEnergy);

    expect($estimate->averageDailyCalories)->toBe(2400)
        ->and($estimate->observedDays)->toBe(15);
});

test('only considers the most recent twenty one days', function () {
    $dailyEnergy = [
        ...appleHealthEnergyDays(4, 9000, startOffset: 21),
        ...appleHealthEnergyDays(21, 2400),
    ];

    $estimate = (new EnergyExpenditureEstimator)->estimate(2000, $dailyEnergy);

    expect($estimate->averageDailyCalories)->toBe(2400)
        ->and($estimate->observedDays)->toBe(21);
});
