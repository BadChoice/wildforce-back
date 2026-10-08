<?php

namespace App\Enums;

enum BudgetSensitivity: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

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
            self::Low => __('Low'),
            self::Medium => __('Medium'),
            self::High => __('High'),
        };
    }
}
