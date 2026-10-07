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

    public function display(): string
    {
        return __($this->value);
    }

    public static function allCasesArray(): array
    {
        return array_column(self::cases(), 'value');
    }
}
