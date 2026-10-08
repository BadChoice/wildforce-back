<?php

namespace App\Enums;

enum WorkoutBlockType: string
{
    case Warmup = 'warmup';
    case Standard = 'standard';
    case Superset = 'superset';
    case Cooldown = 'cooldown';

    /**
     * @return list<string>
     */
    public static function allCasesArray(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Warmup => 'Warmup',
            self::Standard => 'Standard',
            self::Superset => 'Superset',
            self::Cooldown => 'Cooldown',
        };
    }
}
