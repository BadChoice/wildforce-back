<?php

namespace App\Enums;

enum DietaryStyle: string
{
    case Standard = 'standard';
    case Vegetarian = 'vegetarian';
    case Vegan = 'vegan';
    case Pescatarian = 'pescatarian';

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
            self::Standard => __('Standard'),
            self::Vegetarian => __('Vegetarian'),
            self::Vegan => __('Vegan'),
            self::Pescatarian => __('Pescatarian'),
        };
    }
}
