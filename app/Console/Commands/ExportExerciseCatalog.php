<?php

namespace App\Console\Commands;

use App\Services\ExerciseCatalog\ExerciseCatalog;
use App\Services\ExerciseCatalog\IOSExerciseCatalogExporter;
use App\Services\ExerciseCatalog\PHPExerciseCatalogExporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('app:export-exercise-catalog {--ios : Generate the iOS Exercise.swift and ExerciseCatalog.swift files} {--php : Generate the PHP muscle group enums}')]
#[Description('Generate mobile exercise catalog source files')]
class ExportExerciseCatalog extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ExerciseCatalog $exerciseCatalog, IOSExerciseCatalogExporter $iosExporter, PHPExerciseCatalogExporter $phpExporter): int
    {
        if (! $this->option('ios') && ! $this->option('php')) {
            $this->components->error('Select at least one platform: --ios or --php.');

            return self::FAILURE;
        }

        if ($this->option('ios') && (string) config('exercise_catalog.ios_path') === '') {
            $this->components->error('The EXERCISE_CATALOG_IOS_PATH environment variable is not configured.');

            return self::FAILURE;
        }

        try {
            if ($this->option('ios')) {
                $iosExporter->export($exerciseCatalog->all(), (string) config('exercise_catalog.ios_path'));
            }
            if ($this->option('php')) {
                $phpExporter->export($exerciseCatalog->all(), (string) config('exercise_catalog.php_path'));
            }
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->info('Generated exercise catalog source files.');

        return self::SUCCESS;
    }
}
