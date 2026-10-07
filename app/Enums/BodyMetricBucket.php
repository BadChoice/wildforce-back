<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * Period length used to average body metric entries into a single chart point.
 */
enum BodyMetricBucket
{
    case Day;
    case Week;
    case Month;
    case Quarter;

    public function periodStart(CarbonImmutable $date): CarbonImmutable
    {
        return match ($this) {
            self::Day => $date->startOfDay(),
            self::Week => $date->startOfWeek(CarbonImmutable::MONDAY),
            self::Month => $date->startOfMonth(),
            self::Quarter => $date->startOfQuarter(),
        };
    }

    public function periodEnd(CarbonImmutable $date): CarbonImmutable
    {
        return match ($this) {
            self::Day => $date->endOfDay(),
            self::Week => $date->endOfWeek(CarbonImmutable::SUNDAY),
            self::Month => $date->endOfMonth(),
            self::Quarter => $date->endOfQuarter(),
        };
    }

    public function formatPeriod(CarbonImmutable $periodStart): string
    {
        return match ($this) {
            self::Day, self::Week => $periodStart->format('d/m/Y'),
            self::Month => $periodStart->format('m/Y'),
            self::Quarter => 'Q'.$periodStart->quarter.' '.$periodStart->year,
        };
    }
}
