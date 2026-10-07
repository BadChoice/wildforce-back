<?php

namespace App\ValueObjects\BodyMetrics;

use App\Enums\BodyMetricBucket;
use App\Models\BodyMetricEntry;
use Carbon\CarbonImmutable;

/**
 * Bucketed history of one body metric type within a selected range.
 */
final readonly class BodyMetricSeries
{
    /**
     * @param  list<BodyMetricPoint>  $points
     */
    public function __construct(
        public string $type,
        public BodyMetricBucket $bucket,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public array $points,
        public BodyMetricEntry $latestEntry,
    ) {}

    public function isEmpty(): bool
    {
        return $this->points === [];
    }

    public function minimum(): ?float
    {
        return $this->isEmpty() ? null : min(array_map(fn (BodyMetricPoint $point): float => $point->average, $this->points));
    }

    public function maximum(): ?float
    {
        return $this->isEmpty() ? null : max(array_map(fn (BodyMetricPoint $point): float => $point->average, $this->points));
    }

    /**
     * Position of a point along the series time axis, from 0 (start) to 1 (end).
     */
    public function timePosition(BodyMetricPoint $point): float
    {
        $span = $this->endsAt->getTimestamp() - $this->startsAt->getTimestamp();

        if ($span <= 0) {
            return 0.5;
        }

        return min(max(($point->periodStart->getTimestamp() - $this->startsAt->getTimestamp()) / $span, 0), 1);
    }
}
