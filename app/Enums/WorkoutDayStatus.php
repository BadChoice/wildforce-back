<?php

namespace App\Enums;

enum WorkoutDayStatus: string
{
    case Draft = 'draft';
    case Planned = 'planned';
    case InProgress = 'inProgress';
    case Completed = 'completed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Planned => __('Planned'),
            self::InProgress => __('In Progress'),
            self::Completed => __('Completed'),
            self::Skipped => __('Skipped'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'teal',
            self::Planned => 'zinc',
            self::InProgress => 'yellow',
            self::Completed => 'emerald',
            self::Skipped => 'amber',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Draft => 'document',
            self::Planned => 'calendar',
            self::InProgress => 'play-circle',
            self::Completed => 'check-circle',
            self::Skipped => 'forward',
        };
    }

    public function isDefault(): bool
    {
        return $this === self::Planned;
    }
}
