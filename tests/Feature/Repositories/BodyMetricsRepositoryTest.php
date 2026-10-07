<?php

use App\Enums\BodyMetricBucket;
use App\Enums\BodyMetricRange;
use App\Models\BodyMetricEntry;
use App\Models\User;
use App\Repositories\BodyMetricsRepository;
use App\ValueObjects\BodyMetrics\BodyMetricPoint;

test('it averages the entries in range per week for the three month range', function () {
    $this->travelTo('2026-10-07 12:00:00');
    $user = User::factory()->create(['timezone' => 'UTC']);
    bodyMetric($user, 'weight', 90, '2026-06-01 08:00:00');
    bodyMetric($user, 'weight', 80, '2026-09-28 08:00:00');
    bodyMetric($user, 'weight', 81, '2026-10-01 08:00:00');
    bodyMetric($user, 'weight', 79, '2026-10-06 08:00:00');

    $series = app(BodyMetricsRepository::class)->history($user, BodyMetricRange::ThreeMonths)->get('weight');

    expect($series->bucket)->toBe(BodyMetricBucket::Week)
        ->and(array_map(fn (BodyMetricPoint $point): array => [$point->periodStart->toDateString(), $point->average, $point->entryCount], $series->points))
        ->toBe([
            ['2026-09-28', 80.5, 2],
            ['2026-10-05', 79.0, 1],
        ]);
});

test('it picks the bucket size for each range', function (BodyMetricRange $range, string $firstRecordedAt, BodyMetricBucket $expectedBucket) {
    $this->travelTo('2026-10-07 12:00:00');
    $user = User::factory()->create();
    bodyMetric($user, 'weight', 80, $firstRecordedAt);
    bodyMetric($user, 'weight', 81, '2026-10-06 08:00:00');

    $series = app(BodyMetricsRepository::class)->history($user, $range)->get('weight');

    expect($series->bucket)->toBe($expectedBucket);
})->with([
    '7 days' => [BodyMetricRange::Week, '2026-10-05 08:00:00', BodyMetricBucket::Day],
    '1 month' => [BodyMetricRange::Month, '2026-09-10 08:00:00', BodyMetricBucket::Day],
    '1 year' => [BodyMetricRange::Year, '2026-01-10 08:00:00', BodyMetricBucket::Month],
    'all time within three years' => [BodyMetricRange::AllTime, '2024-01-10 08:00:00', BodyMetricBucket::Month],
    'all time beyond three years' => [BodyMetricRange::AllTime, '2022-01-10 08:00:00', BodyMetricBucket::Quarter],
]);

test('it buckets entries by the user timezone', function () {
    $this->travelTo('2026-10-07 12:00:00');
    $user = User::factory()->create(['timezone' => 'Europe/Madrid']);
    bodyMetric($user, 'weight', 80, '2026-10-05 23:30:00');

    $series = app(BodyMetricsRepository::class)->history($user, BodyMetricRange::Week)->get('weight');

    expect($series->points[0]->periodStart->toDateString())->toBe('2026-10-06');
});

test('it keeps metric types without entries in range with their latest entry', function () {
    $this->travelTo('2026-10-07 12:00:00');
    $user = User::factory()->create();
    bodyMetric($user, 'waist', 85, '2026-01-10 08:00:00');
    bodyMetric($user, 'waist', 84, '2026-03-10 08:00:00');
    bodyMetric($user, 'weight', 80, '2026-10-06 08:00:00');

    $history = app(BodyMetricsRepository::class)->history($user, BodyMetricRange::Week);

    expect($history->keys()->all())->toBe(['waist', 'weight'])
        ->and($history->get('waist')->isEmpty())->toBeTrue()
        ->and((float) $history->get('waist')->latestEntry->value)->toBe(84.0);
});

test('it ignores entries of other users and deleted entries', function () {
    $this->travelTo('2026-10-07 12:00:00');
    $user = User::factory()->create();
    bodyMetric($user, 'weight', 80, '2026-10-05 08:00:00');
    bodyMetric($user, 'weight', 70, '2026-10-06 08:00:00')->delete();
    bodyMetric(User::factory()->create(), 'weight', 60, '2026-10-06 08:00:00');

    $series = app(BodyMetricsRepository::class)->history($user, BodyMetricRange::Week)->get('weight');

    expect(array_map(fn (BodyMetricPoint $point): float => $point->average, $series->points))->toBe([80.0])
        ->and((float) $series->latestEntry->value)->toBe(80.0);
});

function bodyMetric(User $user, string $type, float $value, string $recordedAt): BodyMetricEntry
{
    return BodyMetricEntry::factory()->for($user)->create([
        'type' => $type,
        'value' => $value,
        'recorded_at' => $recordedAt,
    ]);
}
