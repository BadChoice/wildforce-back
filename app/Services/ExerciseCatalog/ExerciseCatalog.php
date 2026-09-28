<?php

namespace App\Services\ExerciseCatalog;

use Illuminate\Support\Arr;
use JsonException;

class ExerciseCatalog
{
    private mixed $catalog = null;

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function all(): array
    {
        if ($this->catalog != null) {
            return $this->catalog;
        }

        $contents = file_get_contents(resource_path('exercise-catalog/v1/catalog.json'));

        if ($contents === false) {
            throw new \RuntimeException('The exercise catalog could not be read.');
        }

        /** @var array<string, mixed> $catalog */
        $this->catalog = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $this->catalog;
    }

    /**
     * @param  array<string, mixed>  $exercise
     */
    public function imageUrl(string $exercise, string $gender = 'female'): string
    {
        return rtrim((string) config('exercise_catalog.image_base_url'), '/').'/vertical/'.$exercise.'_'.$gender.'.jpeg';
    }
}
