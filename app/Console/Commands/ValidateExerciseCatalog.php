<?php

namespace App\Console\Commands;

use App\Services\ExerciseCatalog\ExerciseCatalog;
use App\Services\ExerciseCatalog\ExerciseCatalogValidator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('app:validate-exercise-catalog')]
#[Description('Validate the versioned exercise catalog')]
class ValidateExerciseCatalog extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ExerciseCatalog $exerciseCatalog, ExerciseCatalogValidator $validator): int
    {
        try {
            $errors = $validator->validate($exerciseCatalog->all());
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $this->components->info('Exercise catalog is valid.');

        return self::SUCCESS;
    }
}
