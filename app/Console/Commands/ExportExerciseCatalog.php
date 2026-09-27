<?php

namespace App\Console\Commands;

use App\Services\ExerciseCatalog\ExerciseCatalog;
use App\Services\ExerciseCatalog\IOSExerciseCatalogExporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('app:export-exercise-catalog {--ios : Generate the iOS Exercise.swift and ExerciseCatalog.swift files}')]
#[Description('Generate mobile exercise catalog source files')]
class ExportExerciseCatalog extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ExerciseCatalog $exerciseCatalog, IOSExerciseCatalogExporter $iosExporter): int
    {
        if (! $this->option('ios')) {
            $this->components->error('Select at least one platform: --ios.');

            return self::FAILURE;
        }

        $directory = (string) config('exercise_catalog.ios_path');
        if ($directory === '') {
            $this->components->error('The EXERCISE_CATALOG_IOS_PATH environment variable is not configured.');

            return self::FAILURE;
        }

        try {
            $iosExporter->export($exerciseCatalog->all(), $directory);
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info("Generated iOS exercise catalog files at {$directory}.");

        return self::SUCCESS;
    }
}
