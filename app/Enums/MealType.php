<?php

namespace App\Enums;

enum MealType: string
{
    case Breakfast = 'breakfast';
    case Lunch = 'lunch';
    case Dinner = 'dinner';
    case Snack = 'snack';
    case PreWorkout = 'preWorkout';
    case PostWorkout = 'postWorkout';

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
            self::Breakfast => __('Breakfast'),
            self::Lunch => __('Lunch'),
            self::Dinner => __('Dinner'),
            self::Snack => __('Snack'),
            self::PreWorkout => __('Pre-workout'),
            self::PostWorkout => __('Post-workout'),
        };
    }
}
