<?php

use App\Models\BodyMetricEntry;
use App\Models\TrainingLocation;
use App\Models\TrainingPreference;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

test('it returns changed records and soft-deleted tombstones for the authenticated user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $location = makeTrainingLocation($user, 'Home gym', '2026-09-22T12:00:00Z');
    $deletedLocation = makeTrainingLocation($user, 'Closed gym', '2026-09-22T13:00:00Z');
    $deletedLocation->forceFill(['deleted_at' => Carbon::parse('2026-09-22T13:00:00Z')]);
    $deletedLocation->timestamps = false;
    $deletedLocation->save();
    $otherLocation = makeTrainingLocation($otherUser, 'Private gym', '2026-09-22T14:00:00Z');
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/sync/pull?resource=training-locations&updated_after=2026-09-22T11%3A00%3A00Z');

    $response->assertOk()
        ->assertJsonPath('meta.resource', 'training-locations')
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.user_id', $user->id)
        ->assertJsonFragment(['id' => $location->id, 'deleted_at' => null])
        ->assertJsonFragment(['id' => $deletedLocation->id])
        ->assertJsonMissing(['id' => $otherLocation->id]);

    expect($response->json('data.1.deleted_at'))->not->toBeNull();
});

test('it pulls only the authenticated user body metric entries', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $bodyMetricEntry = makeBodyMetricEntry($user, '2026-09-30T12:00:00Z');
    $otherBodyMetricEntry = makeBodyMetricEntry($otherUser, '2026-09-30T12:00:00Z');
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/sync/pull?resource=body-metric-entries&updated_after=2026-09-30T11%3A00%3A00Z');

    $response->assertOk()
        ->assertJsonPath('meta.resource', 'body-metric-entries')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $bodyMetricEntry->id)
        ->assertJsonPath('data.0.user_id', $user->id)
        ->assertJsonPath('data.0.type', 'weight')
        ->assertJsonPath('data.0.value', 74.5)
        ->assertJsonPath('data.0.recorded_at', '2026-09-30T07:30:00.000000Z')
        ->assertJsonMissing(['id' => $otherBodyMetricEntry->id]);
});

test('it returns 401 when no token is provided', function () {
    $response = $this->getJson('/api/sync/pull?resource=training-locations');

    $response->assertUnauthorized();
});

test('it returns every changed record without a pagination limit', function () {
    $user = User::factory()->create();

    foreach (range(1, 101) as $index) {
        makeTrainingLocation($user, "Gym {$index}", '2026-09-22T12:00:00Z');
    }

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/sync/pull?resource=training-locations');

    $response->assertOk()
        ->assertJsonCount(101, 'data');
});

test('it pulls the authenticated user subscription without sensitive provider details', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $subscription = $user->subscription;
    $subscription->forceFill([
        'auto_renews' => true,
        'provider_reference' => 'private-provider-reference',
        'starts_at' => Carbon::parse('2026-09-22T12:00:00Z'),
        'renews_at' => Carbon::parse('2026-10-22T12:00:00Z'),
        'updated_at' => Carbon::parse('2026-09-22T12:00:00Z'),
    ])->save();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/sync/pull?resource=subscriptions');

    $response->assertOk()
        ->assertJsonPath('meta.resource', 'subscriptions')
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $subscription->id)
        ->assertJsonPath('data.0.user_id', $user->id)
        ->assertJsonPath('data.0.plan', 'trial')
        ->assertJsonPath('data.0.provider', 'internal')
        ->assertJsonPath('data.0.status', 'active')
        ->assertJsonPath('data.0.auto_renews', true)
        ->assertJsonMissing(['id' => $otherUser->subscription->id])
        ->assertJsonMissing(['provider_reference' => 'private-provider-reference']);
});

test('it serializes empty custom workout focuses as an object', function () {
    $user = User::factory()->create();
    $preferences = new TrainingPreference;
    $preferences->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'workout_days' => [],
        'custom_workout_focuses' => [],
    ]);
    $preferences->save();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/sync/pull?resource=training-preferences');

    $response->assertOk();
    expect($response->getContent())->toContain('"custom_workout_focuses":{}');
});

function makeTrainingLocation(User $user, string $name, string $updatedAt): TrainingLocation
{
    $location = new TrainingLocation;
    $location->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'name' => $name,
        'equipment' => ['dumbbells'],
        'created_at' => Carbon::parse($updatedAt),
        'updated_at' => Carbon::parse($updatedAt),
    ]);
    $location->timestamps = false;
    $location->save();

    return $location;
}

function makeBodyMetricEntry(User $user, string $updatedAt): BodyMetricEntry
{
    $bodyMetricEntry = new BodyMetricEntry;
    $bodyMetricEntry->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'type' => 'weight',
        'value' => 74.5,
        'recorded_at' => Carbon::parse('2026-09-30T07:30:00Z'),
        'source' => 'manual',
        'created_at' => Carbon::parse($updatedAt),
        'updated_at' => Carbon::parse($updatedAt),
    ]);
    $bodyMetricEntry->timestamps = false;
    $bodyMetricEntry->save();

    return $bodyMetricEntry;
}
