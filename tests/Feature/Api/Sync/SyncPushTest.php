<?php

use App\Models\TrainingLocation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

test('it creates a user-owned record and returns its synchronized state', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $locationId = (string) Str::uuid();

    $response = $this->postJson('/api/sync/push', [
        'resource' => 'training-locations',
        'records' => [[
            'id' => $locationId,
            'created_at' => '2026-09-22T12:00:00Z',
            'updated_at' => '2026-09-22T12:00:00Z',
            'deleted_at' => null,
            'name' => 'Home gym',
            'equipment' => ['dumbbells', 'flatBench'],
            'is_default' => true,
            'sort_order' => 0,
        ]],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.0.id', $locationId)
        ->assertJsonPath('data.0.name', 'Home gym')
        ->assertJsonPath('meta.resource', 'training-locations');

    $this->assertDatabaseHas('training_locations', [
        'id' => $locationId,
        'user_id' => $user->id,
        'name' => 'Home gym',
        'is_default' => true,
    ]);
});

test('it keeps the server version when it is newer than the pushed record', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $location = new TrainingLocation;
    $location->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'name' => 'Server gym',
        'equipment' => ['dumbbells'],
        'created_at' => Carbon::parse('2026-09-22T12:00:00Z'),
        'updated_at' => Carbon::parse('2026-09-22T14:00:00Z'),
    ]);
    $location->timestamps = false;
    $location->save();

    $response = $this->postJson('/api/sync/push', [
        'resource' => 'training-locations',
        'records' => [[
            'id' => $location->id,
            'created_at' => '2026-09-22T12:00:00Z',
            'updated_at' => '2026-09-22T13:00:00Z',
            'deleted_at' => null,
            'name' => 'Stale iPhone gym',
            'equipment' => ['barbell'],
        ]],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.0.name', 'Server gym');

    expect($location->fresh()->name)->toBe('Server gym');
});

test('it returns 401 when no token is provided', function () {
    $response = $this->postJson('/api/sync/push', []);

    $response->assertUnauthorized();
});

test('it rejects attributes that are not synchronizable', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/sync/push', [
        'resource' => 'training-locations',
        'records' => [[
            'id' => (string) Str::uuid(),
            'created_at' => '2026-09-22T12:00:00Z',
            'updated_at' => '2026-09-22T12:00:00Z',
            'deleted_at' => null,
            'user_id' => User::factory()->create()->id,
        ]],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['records.0']);
});

test('it rejects updates to a record owned by another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $location = new TrainingLocation;
    $location->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $otherUser->id,
        'name' => 'Private gym',
        'equipment' => ['dumbbells'],
        'created_at' => Carbon::parse('2026-09-22T12:00:00Z'),
        'updated_at' => Carbon::parse('2026-09-22T12:00:00Z'),
    ]);
    $location->timestamps = false;
    $location->save();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/sync/push', [
        'resource' => 'training-locations',
        'records' => [[
            'id' => $location->id,
            'created_at' => '2026-09-22T12:00:00Z',
            'updated_at' => '2026-09-22T13:00:00Z',
            'deleted_at' => null,
            'name' => 'Hijacked gym',
            'equipment' => ['barbell'],
        ]],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['records']);

    expect($location->fresh()->name)->toBe('Private gym');
});
