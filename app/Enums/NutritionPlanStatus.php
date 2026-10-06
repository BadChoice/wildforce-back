<?php

namespace App\Enums;

enum NutritionPlanStatus: string
{
    case Upcoming = 'upcoming';
    case Active = 'active';
    case Past = 'past';

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => __('Upcoming'),
            self::Active => __('Active'),
            self::Past => __('Past'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Upcoming => 'sky',
            self::Active => 'green',
            self::Past => 'zinc',
        };
    }
}
