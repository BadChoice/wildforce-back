<?php

use App\Services\ExerciseCatalog\ExerciseCatalog;
use App\Services\ExerciseCatalog\IOSExerciseCatalogExporter;

test('exports the canonical catalog as Swift source files', function () {
    $directory = sys_get_temp_dir().'/exercise-catalog-ios-'.uniqid();

    app(IOSExerciseCatalogExporter::class)->export(app(ExerciseCatalog::class)->all(), $directory);

    expect(file_get_contents("{$directory}/Exercise.swift"))
        ->toContain('public enum Exercise: String, Codable, CaseIterable')
        ->toContain('case walking')
        ->toContain('case neckCircles')
        ->toContain('func remoteImage(gender: Gender = .female) -> URL?')
        ->and(file_get_contents("{$directory}/ExerciseCatalog.swift"))
        ->toContain('.walking: ExerciseMetadata(')
        ->toContain('englishName: "Walking"')
        ->toContain('.neckCircles: ExerciseMetadata(')
        ->toContain('public static func relevantExercises(');
});

test('generates the iOS catalog through the Artisan command', function () {
    $directory = sys_get_temp_dir().'/exercise-catalog-command-'.uniqid();
    config()->set('exercise_catalog.ios_path', $directory);

    $this->artisan('app:export-exercise-catalog', ['--ios' => true])
        ->assertSuccessful();

    expect(file_get_contents("{$directory}/Exercise.swift"))
        ->toContain('case walking');
});
