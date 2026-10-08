<?php

namespace App\Enums;

enum WorkoutDayType: string
{
    case Strength = 'strength';
    case Hypertrophy = 'hypertrophy';
    case Technique = 'technique';
    case Volume = 'volume';
    case Deload = 'deload';
    case Recovery = 'recovery';
    case Conditioning = 'conditioning';

    /**
     * @return list<string>
     */
    public static function allCasesArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
