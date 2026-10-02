<?php

namespace App\Enums;

enum ExerciseSetStyle: string
{
    case Warmup = 'warmup';
    case Straight = 'straight';
    case TopSetBackoff = 'topSetBackoff';
    case AscendingPyramid = 'ascendingPyramid';
    case DropSet = 'dropSet';
    case RestPause = 'restPause';
    case Intervals = 'intervals';
    case Tempo = 'tempo';

    /**
     * @return list<string>
     */
    public function allowedFields(): array
    {
        return match ($this) {
            self::Warmup, self::Straight, self::AscendingPyramid => [
                'target_rir',
            ],
            self::TopSetBackoff => [
                'backoff_set_count',
                'backoff_weight_percent',
                'target_rir',
                'applies_to_final_set_only',
            ],
            self::DropSet => [
                'drop_count',
                'drop_weight_percent',
                'applies_to_final_set_only',
                'target_rir',
            ],
            self::RestPause, self::Intervals => [
                'intra_set_rest_seconds',
                'target_rir',
            ],
            self::Tempo => [
                'tempo',
                'target_rir',
            ],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Warmup => 'Warmup',
            self::Straight => 'Straight',
            self::TopSetBackoff => 'Top Set / Backoff',
            self::AscendingPyramid => 'Ascending Pyramid',
            self::DropSet => 'Drop Set',
            self::RestPause => 'Rest Pause',
            self::Intervals => 'Intervals',
            self::Tempo => 'Tempo',
        };
    }

    /**
     * @param  mixed  $config
     * @return array<string, mixed>|null
     */
    public static function formatConfiguration(mixed $config): ?array
    {
        if (! is_array($config) || empty($config['style'])) {
            return null;
        }

        $style = self::tryFrom((string) $config['style']);
        if ($style === null) {
            return null;
        }

        $allowed = $style->allowedFields();
        $filtered = ['style' => $style->value];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $config) && $config[$field] !== '' && $config[$field] !== null) {
                if ($field === 'applies_to_final_set_only') {
                    $filtered[$field] = (bool) $config[$field];
                } elseif (in_array($field, ['drop_count', 'backoff_set_count', 'intra_set_rest_seconds', 'target_rir'], true)) {
                    $filtered[$field] = (int) $config[$field];
                } elseif (in_array($field, ['drop_weight_percent', 'backoff_weight_percent'], true)) {
                    $filtered[$field] = (float) $config[$field];
                } else {
                    $filtered[$field] = (string) $config[$field];
                }
            }
        }

        return $filtered;
    }
}
