<?php

use App\Models\ExerciseResult;
use App\Services\Workouts\Progression\ExerciseTrendCalculator;

test('scores each session by its best set rather than its last set', function () {
    $trend = (new ExerciseTrendCalculator)->trend(collect([
        trendResult([100, 60], [5, 12]),
        trendResult([105, 60], [5, 12]),
    ]));

    expect($trend)->toBe('improving');
});

test('treats the same load reported as much harder as a regression', function () {
    $trend = (new ExerciseTrendCalculator)->trend(collect([
        trendResult([100, 100, 100], [8, 8, 8], 'easy'),
        trendResult([100, 100, 100], [8, 8, 8], 'veryHard'),
    ]));

    expect($trend)->toBe('regressing');
});

test('ignores an isolated value far from both neighbours', function () {
    $trend = (new ExerciseTrendCalculator)->trend(collect([
        trendResult([30], [10]),
        trendResult([16], [10]),
        trendResult([30], [10]),
        trendResult([30.5], [10]),
    ]));

    expect($trend)->toBe('plateau');
});

test('tracks bodyweight exercises by reps', function () {
    $trend = (new ExerciseTrendCalculator)->trend(collect([
        trendResult(null, [8, 8, 8]),
        trendResult(null, [10, 10, 10]),
    ]));

    expect($trend)->toBe('improving');
});

test('needs at least two sessions', function () {
    expect((new ExerciseTrendCalculator)->trend(collect([trendResult([100], [5])])))->toBe('insufficient')
        ->and((new ExerciseTrendCalculator)->trend(collect()))->toBe('insufficient');
});

/**
 * @param  list<float>|null  $weights
 * @param  list<int>  $reps
 */
function trendResult(?array $weights, array $reps, string $feedback = 'justRight'): ExerciseResult
{
    return (new ExerciseResult)->forceFill([
        'feedback' => $feedback,
        'completed_sets' => count($reps),
        'completed_reps' => $reps[array_key_last($reps)],
        'completed_weight' => $weights === null ? 0 : $weights[array_key_last($weights)],
        'per_set_reps' => $reps,
        'per_set_weights_kg' => $weights,
    ]);
}
