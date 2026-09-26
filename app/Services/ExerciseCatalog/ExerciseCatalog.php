<?php

namespace App\Services\ExerciseCatalog;

use JsonException;

class ExerciseCatalog
{
    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function all(): array
    {
        $contents = file_get_contents(resource_path('exercise-catalog/v1/catalog.json'));

        if ($contents === false) {
            throw new \RuntimeException('The exercise catalog could not be read.');
        }

        /** @var array<string, mixed> $catalog */
        $catalog = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $catalog;
    }
}
