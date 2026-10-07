<?php

namespace App\Enums;

enum WorkoutFocus: string
{
    case FullBody = 'fullBody';
    case UpperBody = 'upperBody';
    case LowerBody = 'lowerBody';
    case Push = 'push';
    case Pull = 'pull';
    case Legs = 'legs';
    case Core = 'core';
    case Cardio = 'cardio';
    case Mobility = 'mobility';
    case Recovery = 'recovery';

    public function label(): string
    {
        return match ($this) {
            self::FullBody => __('Full body'),
            self::UpperBody => __('Upper body'),
            self::LowerBody => __('Lower body'),
            self::Push => __('Push'),
            self::Pull => __('Pull'),
            self::Legs => __('Legs'),
            self::Core => __('Core'),
            self::Cardio => __('Cardio'),
            self::Mobility => __('Mobility'),
            self::Recovery => __('Recovery'),
        };
    }

    /**
     * @return list<string>
     */
    public static function allCasesArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
