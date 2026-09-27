<?php

namespace App\Services\ExerciseCatalog;

use Illuminate\Support\Arr;
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

    /**
     * @param  array<string, mixed>  $exercise
     */
    public function imageUrl(array $exercise, string $gender = 'female'): string
    {
        $key = str_replace(
            '{gender}',
            $gender,
            (string) Arr::get($exercise, 'assets.verticalImageKey'),
        );

        return rtrim((string) config('exercise_catalog.image_base_url'), '/').'/vertical/'.$key;
    }
}
