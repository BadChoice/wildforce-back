<?php

use App\Models\TrainingPreference;

test('needs mobility exercises only for cooldowns or a mobility goal or focus', function (array $attributes, bool $expected) {
    $preferences = TrainingPreference::factory()->make([
        'goal' => 'buildMuscle',
        'skips_warmups' => true,
        'skips_cooldowns' => true,
        'custom_workout_focuses' => ['monday' => 'push'],
        ...$attributes,
    ]);

    expect($preferences->needsMobilityExercises())->toBe($expected);
})->with([
    'skips warmups and cooldowns' => [[], false],
    'does warmups only' => [['skips_warmups' => false], false],
    'does cooldowns' => [['skips_cooldowns' => false], true],
    'mobility goal' => [['goal' => 'improveMobility'], true],
    'mobility day focus' => [['custom_workout_focuses' => ['monday' => 'mobility']], true],
]);
