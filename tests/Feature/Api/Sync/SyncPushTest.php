<?php

use App\Models\PlannedExercise;
use App\Models\TrainingLocation;
use App\Models\User;
use App\Models\WorkoutDay;
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

test('it synchronizes a body metric entry for the authenticated user', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $bodyMetricEntryId = (string) Str::uuid();

    $response = $this->postJson('/api/sync/push', [
        'resource' => 'body-metric-entries',
        'records' => [[
            'id' => $bodyMetricEntryId,
            'created_at' => '2026-09-30T12:00:00Z',
            'updated_at' => '2026-09-30T12:00:00Z',
            'type' => 'weight',
            'value' => 74.5,
            'recorded_at' => '2026-09-30T07:30:00Z',
            'source' => 'manual',
        ]],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.0.id', $bodyMetricEntryId)
        ->assertJsonPath('data.0.type', 'weight')
        ->assertJsonPath('data.0.value', 74.5)
        ->assertJsonPath('data.0.recorded_at', '2026-09-30T07:30:00.000000Z')
        ->assertJsonPath('meta.resource', 'body-metric-entries');

    $this->assertDatabaseHas('body_metric_entries', [
        'id' => $bodyMetricEntryId,
        'user_id' => $user->id,
        'type' => 'weight',
        'value' => 74.5,
        'source' => 'manual',
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

test('it does not rewrite an existing primary key when UUID casing differs', function () {
    $user = User::factory()->create();
    $location = new TrainingLocation;
    $location->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'name' => 'Home gym',
        'equipment' => ['dumbbells'],
    ]);
    $location->save();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/sync/push', [
        'resource' => 'users',
        'records' => [[
            'id' => strtoupper($user->id),
            'created_at' => '2030-09-22T12:00:00Z',
            'updated_at' => '2030-09-23T12:00:00Z',
            'name' => 'Updated user',
            'height' => 170,
            'weight' => 70,
            'birth_date' => '1990-01-01',
            'gender' => 'male',
            'language' => 'en',
            'metric_system' => 'metric',
            'current_streak' => 0,
            'longest_streak' => 0,
            'xp' => 25,
            'xp_level' => 1,
        ]],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.0.name', 'Updated user')
        ->assertJsonPath('data.0.height', 170)
        ->assertJsonPath('data.0.weight', 70);

    expect($location->fresh()->user_id)->toBe($user->id);
});

test('it lowercases UUID foreign keys so pushed records attach to their parent', function () {
    $user = User::factory()->create();
    $plannedExercise = PlannedExercise::factory()
        ->for(WorkoutDay::factory()->for($user))
        ->create();
    Sanctum::actingAs($user);
    $exerciseResultId = (string) Str::uuid();

    $response = $this->postJson('/api/sync/push', [
        'resource' => 'exercise-results',
        'records' => [[
            'id' => strtoupper($exerciseResultId),
            'created_at' => '2026-10-05T14:44:00Z',
            'updated_at' => '2026-10-05T14:44:05Z',
            'planned_exercise_id' => strtoupper($plannedExercise->id),
            'completed_at' => '2026-10-05T14:44:00Z',
            'completed_sets' => 3,
            'completed_reps' => 8,
            'per_set_reps' => [8, 8, 8],
        ]],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.0.planned_exercise_id', $plannedExercise->id);

    expect($plannedExercise->load('exerciseResults')->exerciseResults->pluck('id')->all())
        ->toBe([$exerciseResultId]);
});

test('it returns 401 when no token is provided', function () {
    $response = $this->postJson('/api/sync/push', []);

    $response->assertUnauthorized();
});

test('it rejects subscriptions because they can only be pulled', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/sync/push', [
        'resource' => 'subscriptions',
        'records' => [],
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['resource']);
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

test('it synchronizes the user timezone', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/sync/push', [
        'resource' => 'users',
        'records' => [[
            'id' => $user->id,
            'created_at' => '2030-09-22T12:00:00Z',
            'updated_at' => '2030-09-23T12:00:00Z',
            'timezone' => 'Europe/Madrid',
        ]],
    ])
        ->assertOk()
        ->assertJsonPath('data.0.timezone', 'Europe/Madrid');

    expect($user->fresh()->timezone)->toBe('Europe/Madrid');
});
