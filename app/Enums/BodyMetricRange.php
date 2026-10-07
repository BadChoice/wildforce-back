<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * Time window selectable on the body metrics charts.
 */
enum BodyMetricRange: string
{
    case Week = '7d';
    case Month = '1m';
    case ThreeMonths = '3m';
    case Year = '1y';
    case AllTime = 'all';

    /**
     * All-time histories longer than this are averaged per quarter instead of per month.
     */
    private const int AllTimeMonthlyLimitInYears = 3;

    public function label(): string
    {
        return match ($this) {
            self::Week => __('7 days'),
            self::Month => __('1 month'),
            self::ThreeMonths => __('3 months'),
            self::Year => __('1 year'),
            self::AllTime => __('All time'),
        };
    }

    /**
     * First moment included in the range, or null when the range is unbounded.
     */
    public function startsAt(CarbonImmutable $now): ?CarbonImmutable
    {
        $today = $now->startOfDay();

        return match ($this) {
            self::Week => $today->subDays(6),
            self::Month => $today->subMonthNoOverflow(),
            self::ThreeMonths => $today->subMonthsNoOverflow(3),
            self::Year => $today->subYearNoOverflow(),
            self::AllTime => null,
        };
    }

    /**
     * Bucket used to average entries, chosen from the history span for the all-time range.
     */
    public function bucket(?CarbonImmutable $firstRecordedAt, CarbonImmutable $now): BodyMetricBucket
    {
        return match ($this) {
            self::Week, self::Month => BodyMetricBucket::Day,
            self::ThreeMonths => BodyMetricBucket::Week,
            self::Year => BodyMetricBucket::Month,
            self::AllTime => $firstRecordedAt !== null && $firstRecordedAt->lt($now->subYears(self::AllTimeMonthlyLimitInYears))
                ? BodyMetricBucket::Quarter
                : BodyMetricBucket::Month,
        };
    }
}
