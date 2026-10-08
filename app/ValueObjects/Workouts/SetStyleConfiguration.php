<?php

namespace App\ValueObjects\Workouts;

use App\Casts\SetStyleConfigurationCast;
use App\Enums\ExerciseSetStyle;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Support\Arrayable;
use InvalidArgumentException;
use JsonSerializable;

final readonly class SetStyleConfiguration implements Arrayable, Castable, JsonSerializable
{
    public function __construct(
        public ExerciseSetStyle $style,
        public ?bool $appliesToFinalSetOnly = null,
        public ?int $dropCount = null,
        public ?float $dropWeightPercent = null,
        public ?int $backoffSetCount = null,
        public ?float $backoffWeightPercent = null,
        public ?int $intraSetRestSeconds = null,
        public ?string $tempo = null,
        public ?int $targetRir = null,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function fromArray(array $attributes): self
    {
        $style = $attributes['style'] ?? null;

        if (! is_string($style) || ($style = ExerciseSetStyle::tryFrom($style)) === null) {
            throw new InvalidArgumentException('A set style configuration requires a style.');
        }

        return new self(
            style: $style,
            appliesToFinalSetOnly: self::nullableBool($attributes, 'applies_to_final_set_only', 'appliesToFinalSetOnly'),
            dropCount: self::nullableInt($attributes, 'drop_count', 'dropCount'),
            dropWeightPercent: self::nullableFloat($attributes, 'drop_weight_percent', 'dropWeightPercent'),
            backoffSetCount: self::nullableInt($attributes, 'backoff_set_count', 'backoffSetCount'),
            backoffWeightPercent: self::nullableFloat($attributes, 'backoff_weight_percent', 'backoffWeightPercent'),
            intraSetRestSeconds: self::nullableInt($attributes, 'intra_set_rest_seconds', 'intraSetRestSeconds'),
            tempo: self::nullableString($attributes, 'tempo'),
            targetRir: self::nullableInt($attributes, 'target_rir', 'targetRIR'),
        );
    }

    /**
     * @return array<string, bool|float|int|string>
     */
    public function toArray(): array
    {
        return array_filter([
            'style' => $this->style->value,
            'applies_to_final_set_only' => $this->appliesToFinalSetOnly,
            'drop_count' => $this->dropCount,
            'drop_weight_percent' => $this->dropWeightPercent,
            'backoff_set_count' => $this->backoffSetCount,
            'backoff_weight_percent' => $this->backoffWeightPercent,
            'intra_set_rest_seconds' => $this->intraSetRestSeconds,
            'tempo' => $this->tempo,
            'target_rir' => $this->targetRir,
        ], fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  list<string>  $arguments
     * @return class-string<SetStyleConfigurationCast>
     */
    public static function castUsing(array $arguments): string
    {
        return SetStyleConfigurationCast::class;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function nullableBool(array $attributes, string ...$keys): ?bool
    {
        $value = self::value($attributes, ...$keys);

        return $value === null ? null : (bool) $value;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function nullableFloat(array $attributes, string ...$keys): ?float
    {
        $value = self::value($attributes, ...$keys);

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function nullableInt(array $attributes, string ...$keys): ?int
    {
        $value = self::value($attributes, ...$keys);

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function nullableString(array $attributes, string ...$keys): ?string
    {
        $value = self::value($attributes, ...$keys);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function value(array $attributes, string ...$keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $attributes)) {
                return $attributes[$key];
            }
        }

        return null;
    }
}
