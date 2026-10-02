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
}
