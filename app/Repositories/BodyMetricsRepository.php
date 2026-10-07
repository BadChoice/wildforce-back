<?php

namespace App\Repositories;

use App\Enums\BodyMetricBucket;
use App\Enums\BodyMetricRange;
use App\Models\BodyMetricEntry;
use App\Models\User;
use App\ValueObjects\BodyMetrics\BodyMetricPoint;
use App\ValueObjects\BodyMetrics\BodyMetricSeries;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;

class BodyMetricsRepository
{
    /**
     * Bucketed history of every metric type the user has recorded, keyed by type.
     *
     * Types without entries in the range are still returned (with no points) so their latest value stays visible.
     *
     * @return Collection<string, BodyMetricSeries>
     */
    public function history(User $user, BodyMetricRange $range, ?CarbonImmutable $now = null): Collection
    {
        $timezone = $user->preferredTimezone();
        $now = ($now ?? CarbonImmutable::now())->setTimezone($timezone);
        $latestEntries = $this->latestEntries($user);

        if ($latestEntries->isEmpty()) {
            return collect();
        }

        $rangeStartsAt = $range->startsAt($now);
        $entriesByType = $this->entriesSince($user, $rangeStartsAt)->groupBy('type');

        return $latestEntries
            ->sortKeys()
            ->map(function (BodyMetricEntry $latestEntry, string $type) use ($entriesByType, $range, $rangeStartsAt, $now, $timezone): BodyMetricSeries {
                $readings = $entriesByType->get($type, collect())
                    ->map(fn (BodyMetricEntry $entry): array => [
                        'recorded_at' => CarbonImmutable::instance($entry->recorded_at)->setTimezone($timezone),
                        'value' => (float) $entry->value,
                    ]);
                $bucket = $range->bucket($readings->min('recorded_at'), $now);
                $points = $this->bucketPoints($readings, $bucket);

                return new BodyMetricSeries(
                    type: $type,
                    bucket: $bucket,
                    startsAt: $rangeStartsAt !== null ? $bucket->periodStart($rangeStartsAt) : ($points[0]->periodStart ?? $now),
                    endsAt: $now,
                    points: $points,
                    latestEntry: $latestEntry,
                );
            });
    }

    /**
     * Most recent entry of each metric type, keyed by type.
     *
     * @return Collection<string, BodyMetricEntry>
     */
    private function latestEntries(User $user): Collection
    {
        $latestRecordedAtByType = $user->bodyMetrics()
            ->selectRaw('type, max(recorded_at) as latest_recorded_at')
            ->groupBy('type');

        return $user->bodyMetrics()
            ->joinSub($latestRecordedAtByType, 'latest_body_metrics', function (JoinClause $join): void {
                $join->on('body_metric_entries.type', '=', 'latest_body_metrics.type')
                    ->on('body_metric_entries.recorded_at', '=', 'latest_body_metrics.latest_recorded_at');
            })
            ->select(['body_metric_entries.id', 'body_metric_entries.type', 'body_metric_entries.value', 'body_metric_entries.recorded_at'])
            ->get()
            ->unique('type')
            ->keyBy('type');
    }

    /**
     * @return Collection<int, BodyMetricEntry>
     */
    private function entriesSince(User $user, ?CarbonImmutable $startsAt): Collection
    {
        return $user->bodyMetrics()
            ->select(['id', 'type', 'value', 'recorded_at'])
            ->when($startsAt !== null, fn ($query) => $query->where('recorded_at', '>=', $startsAt->utc()))
            ->orderBy('recorded_at')
            ->get();
    }

    /**
     * @param  Collection<int, array{recorded_at: CarbonImmutable, value: float}>  $readings
     * @return list<BodyMetricPoint>
     */
    private function bucketPoints(Collection $readings, BodyMetricBucket $bucket): array
    {
        return $readings
            ->groupBy(fn (array $reading): string => $bucket->periodStart($reading['recorded_at'])->toIso8601String())
            ->map(fn (Collection $bucketEntries): BodyMetricPoint => new BodyMetricPoint(
                periodStart: $bucket->periodStart($bucketEntries->first()['recorded_at']),
                average: round($bucketEntries->avg('value'), 3),
                minimum: $bucketEntries->min('value'),
                maximum: $bucketEntries->max('value'),
                entryCount: $bucketEntries->count(),
            ))
            ->values()
            ->all();
    }
}
