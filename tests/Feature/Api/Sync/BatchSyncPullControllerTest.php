<?php

use App\Models\NutritionLogEntry;
use App\Models\NutritionLogItem;
use App\Models\TrainingLocation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

test('returns 401 when no token is provided', function () {
    $this->postJson('/api/sync/pull/batch', [
        'resources' => [['resource' => 'training-locations', 'updated_after' => null]],
    ])->assertUnauthorized();
});

test('returns 422 for unsupported resources and invalid cursors', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/sync/pull/batch', [
        'resources' => [['resource' => 'workout-days', 'updated_after' => null]],
    ])->assertUnprocessable()->assertJsonValidationErrors(['resources.0.resource']);

    $this->postJson('/api/sync/pull/batch', ['cursor' => 'not-a-valid-cursor'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cursor']);
});

test('returns only the authenticated users records and tombstones', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $location = batchTrainingLocation($user, 'Home gym', '2026-09-30T08:00:00Z');
    $deletedLocation = batchTrainingLocation($user, 'Closed gym', '2026-09-30T08:01:00Z');
    $deletedLocation->forceFill(['deleted_at' => Carbon::parse('2026-09-30T08:01:00Z')]);
    $deletedLocation->timestamps = false;
    $deletedLocation->save();
    $otherLocation = batchTrainingLocation($otherUser, 'Private gym', '2026-09-30T08:02:00Z');
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/sync/pull/batch', [
        'resources' => [['resource' => 'training-locations', 'updated_after' => null]],
    ]);

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.resource', 'training-locations')
        ->assertJsonPath('data.0.record.id', $location->id)
        ->assertJsonPath('data.1.record.id', $deletedLocation->id)
        ->assertJsonMissing(['id' => $otherLocation->id]);

    expect(collect($response->json('data'))->firstWhere('record.id', $deletedLocation->id)['record']['deleted_at'])->not->toBeNull();
});

test('emits nutrition log entries before their items at the same timestamp', function () {
    $user = User::factory()->create();
    $timestamp = '2026-09-30T08:00:00Z';
    $entry = batchNutritionLogEntry($user, $timestamp);
    $item = batchNutritionLogItem($entry, $timestamp);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/sync/pull/batch', [
        'resources' => [
            ['resource' => 'nutrition-log-items', 'updated_after' => null],
            ['resource' => 'nutrition-log-entries', 'updated_after' => null],
        ],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.0.resource', 'nutrition-log-entries')
        ->assertJsonPath('data.0.record.id', $entry->id)
        ->assertJsonPath('data.1.resource', 'nutrition-log-items')
        ->assertJsonPath('data.1.record.id', $item->id);
});

test('uses a fixed as of timestamp and resumes without duplicate records', function () {
    $this->travelTo('2026-09-30T09:00:00Z');
    $user = User::factory()->create();

    foreach (range(1, 101) as $index) {
        batchTrainingLocation($user, "Gym {$index}", '2026-09-30T08:00:00Z');
    }

    $futureLocation = batchTrainingLocation($user, 'Future gym', '2026-09-30T09:01:00Z');
    Sanctum::actingAs($user);

    $firstPage = $this->postJson('/api/sync/pull/batch', [
        'resources' => [['resource' => 'training-locations', 'updated_after' => null]],
    ]);

    $firstPage->assertOk()
        ->assertJsonPath('meta.as_of', '2026-09-30T09:00:00.000000Z')
        ->assertJsonCount(100, 'data')
        ->assertJsonMissing(['id' => $futureLocation->id]);

    $secondPage = $this->postJson('/api/sync/pull/batch', [
        'cursor' => $firstPage->json('meta.next_cursor'),
    ]);

    $secondPage->assertOk()
        ->assertJsonPath('meta.as_of', '2026-09-30T09:00:00.000000Z')
        ->assertJsonPath('meta.next_cursor', null)
        ->assertJsonCount(1, 'data')
        ->assertJsonMissing(['id' => $futureLocation->id]);

    $firstPageIds = collect($firstPage->json('data'))->pluck('record.id');
    $secondPageIds = collect($secondPage->json('data'))->pluck('record.id');

    expect($firstPageIds->intersect($secondPageIds))->toBeEmpty();
});

test('returns 422 when a cursor belongs to another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    foreach (range(1, 101) as $index) {
        batchTrainingLocation($user, "Gym {$index}", '2026-09-30T08:00:00Z');
    }

    Sanctum::actingAs($user);
    $cursor = $this->postJson('/api/sync/pull/batch', [
        'resources' => [['resource' => 'training-locations', 'updated_after' => null]],
    ])->json('meta.next_cursor');
    Sanctum::actingAs($otherUser);

    $this->postJson('/api/sync/pull/batch', ['cursor' => $cursor])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cursor']);
});

test('returns 422 when a cursor has expired', function () {
    $this->travelTo('2026-09-30T09:00:00Z');
    $user = User::factory()->create();

    foreach (range(1, 101) as $index) {
        batchTrainingLocation($user, "Gym {$index}", '2026-09-30T08:00:00Z');
    }

    Sanctum::actingAs($user);
    $cursor = $this->postJson('/api/sync/pull/batch', [
        'resources' => [['resource' => 'training-locations', 'updated_after' => null]],
    ])->json('meta.next_cursor');
    $this->travel(21)->minutes();

    $this->postJson('/api/sync/pull/batch', ['cursor' => $cursor])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['cursor']);
});

function batchTrainingLocation(User $user, string $name, string $updatedAt): TrainingLocation
{
    $location = new TrainingLocation;
    $location->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'name' => $name,
        'equipment' => [],
        'created_at' => Carbon::parse($updatedAt),
        'updated_at' => Carbon::parse($updatedAt),
    ]);
    $location->timestamps = false;
    $location->save();

    return $location;
}

function batchNutritionLogEntry(User $user, string $updatedAt): NutritionLogEntry
{
    $entry = new NutritionLogEntry;
    $entry->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'title' => 'Breakfast',
        'logged_at' => Carbon::parse($updatedAt),
        'created_at' => Carbon::parse($updatedAt),
        'updated_at' => Carbon::parse($updatedAt),
    ]);
    $entry->timestamps = false;
    $entry->save();

    return $entry;
}

function batchNutritionLogItem(NutritionLogEntry $entry, string $updatedAt): NutritionLogItem
{
    $item = new NutritionLogItem;
    $item->forceFill([
        'id' => (string) Str::uuid(),
        'nutrition_log_entry_id' => $entry->id,
        'name' => 'Oats',
        'created_at' => Carbon::parse($updatedAt),
        'updated_at' => Carbon::parse($updatedAt),
    ]);
    $item->timestamps = false;
    $item->save();

    return $item;
}
