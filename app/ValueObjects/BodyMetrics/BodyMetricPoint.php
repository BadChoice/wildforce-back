<?php

namespace App\ValueObjects\BodyMetrics;

use Carbon\CarbonImmutable;

/**
 * Average of the body metric entries recorded within one bucket period.
 */
final readonly class BodyMetricPoint
{
    public function __construct(
        public CarbonImmutable $periodStart,
        public float $average,
        public float $minimum,
        public float $maximum,
        public int $entryCount,
    ) {}
}
