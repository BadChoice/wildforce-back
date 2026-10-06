<?php

namespace App\Enums;

enum NutritionAdherenceStatus: string
{
    case OnTarget = 'onTarget';
    case LowProtein = 'lowProtein';
    case Under = 'under';
    case Over = 'over';
    case InProgress = 'inProgress';
    case NotLogged = 'notLogged';
    case NoPlan = 'noPlan';
    case Upcoming = 'upcoming';

    public function label(): string
    {
        return match ($this) {
            self::OnTarget => __('On target'),
            self::LowProtein => __('Low protein'),
            self::Under => __('Under target'),
            self::Over => __('Over target'),
            self::InProgress => __('In progress'),
            self::NotLogged => __('Not logged'),
            self::NoPlan => __('No plan'),
            self::Upcoming => __('Upcoming'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::OnTarget => 'green',
            self::LowProtein, self::Under => 'amber',
            self::Over => 'red',
            self::InProgress => 'sky',
            self::NotLogged, self::NoPlan, self::Upcoming => 'zinc',
        };
    }
}
