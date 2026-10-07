<?php

namespace App\Casts;

use App\ValueObjects\Workouts\SetStyleConfiguration;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<SetStyleConfiguration|null, mixed>
 */
class SetStyleConfigurationCast implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?SetStyleConfiguration
    {
        if ($value === null) {
            return null;
        }

        $configuration = is_string($value)
            ? json_decode($value, true, 512, JSON_THROW_ON_ERROR)
            : $value;

        if (! is_array($configuration)) {
            return null;
        }

        return SetStyleConfiguration::fromArray($configuration);
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $configuration = match (true) {
            $value instanceof SetStyleConfiguration => $value,
            is_array($value) => SetStyleConfiguration::fromArray($value),
            default => throw new InvalidArgumentException('Set style configuration must be an array or SetStyleConfiguration.'),
        };

        return json_encode($configuration->toArray(), JSON_THROW_ON_ERROR);
    }
}
