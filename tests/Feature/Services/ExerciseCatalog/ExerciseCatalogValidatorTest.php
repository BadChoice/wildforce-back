<?php

use App\Services\ExerciseCatalog\ExerciseCatalog;
use App\Services\ExerciseCatalog\ExerciseCatalogValidator;

test('the versioned exercise catalog is valid', function () {
    $errors = app(ExerciseCatalogValidator::class)->validate(app(ExerciseCatalog::class)->all());

    expect($errors)->toBe([]);
});

test('the validator reports dangling exercise references', function () {
    $catalog = app(ExerciseCatalog::class)->all();
    $catalog['exercises'][0]['primaryMuscles'] = ['unknownMuscle'];

    $errors = app(ExerciseCatalogValidator::class)->validate($catalog);

    expect($errors)->toContain('exercises.0.primaryMuscles references an unknown identifier.');
});
