<?php

use App\Services\ExerciseCatalog\ExerciseCatalog;
use App\Services\ExerciseCatalog\PHPExerciseCatalogExporter;

test('exports the catalog reference enums as PHP source files', function () {
    $directory = sys_get_temp_dir().'/exercise-catalog-php-'.uniqid();

    app(PHPExerciseCatalogExporter::class)->export(app(ExerciseCatalog::class)->all(), $directory);

    expect(file_get_contents("{$directory}/ExerciseCategory.php"))
        ->toContain("case Strength = 'strength';")
        ->and(file_get_contents("{$directory}/ExerciseTargetMetric.php"))
        ->toContain("case DurationSeconds = 'durationSeconds';")
        ->and(file_get_contents("{$directory}/ExerciseTrackingMode.php"))
        ->toContain("case DurationMinutesAndDistance = 'durationMinutesAndDistance';")
        ->and(file_get_contents("{$directory}/MovementPattern.php"))
        ->toContain("case HorizontalPush = 'horizontalPush';");
});
