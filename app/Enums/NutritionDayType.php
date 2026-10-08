<?php

namespace App\Enums;

enum NutritionDayType: string
{
    case Training = 'training';
    case Rest = 'rest';
    case Recovery = 'recovery';

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
            self::Training => __('Training'),
            self::Rest => __('Rest'),
            self::Recovery => __('Recovery'),
        };
    }
}
