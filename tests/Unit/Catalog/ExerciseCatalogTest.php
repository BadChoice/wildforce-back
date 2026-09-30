<?php

use App\Services\ExerciseCatalog\ExerciseCatalog;
use Tests\TestCase;

uses(TestCase::class);

test('can fetch the biomechanics data of an exercise', function () {
    $catalog = new ExerciseCatalog;

    $biomechanics = $catalog->biomechanics('cableHipAbduction');

    expect($biomechanics)->toBeArray();
});
