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

test('it synchronizes a complete workout day graph atomically', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $workoutDayId = (string) Str::uuid();
    $blockId = (string) Str::uuid();
    $exerciseId = (string) Str::uuid();
    $resultId = (string) Str::uuid();

    $response = $this->postJson('/api/sync/workout-days', [
        'records' => [[
            'id' => $workoutDayId,
            'created_at' => '2026-09-24T12:00:00Z',
            'updated_at' => '2026-09-24T12:00:00Z',
            'deleted_at' => null,
            'title' => 'Upper body',
            'focus' => 'upperBody',
            'status' => 'planned',
            'did_count_toward_streak' => false,
            'order_index' => 0,
            'creation_source' => 'generated',
            'blocks' => [[
                'id' => $blockId,
                'created_at' => '2026-09-24T12:00:00Z',
                'updated_at' => '2026-09-24T12:00:00Z',
                'deleted_at' => null,
                'type' => 'standard',
                'order_index' => 0,
                'rounds' => 1,
                'exercises' => [[
                    'id' => $exerciseId,
                    'created_at' => '2026-09-24T12:00:00Z',
                    'updated_at' => '2026-09-24T12:00:00Z',
                    'deleted_at' => null,
                    'exercise' => 'benchPress',
                    'order_index' => 0,
                    'exercise_results' => [[
                        'id' => $resultId,
                        'created_at' => '2026-09-24T12:00:00Z',
                        'updated_at' => '2026-09-24T12:00:00Z',
                        'deleted_at' => null,
                        'feedback' => 'justRight',
                        'completed_at' => '2026-09-24T12:00:00Z',
                    ]],
                ]],
            ]],
            'exercises' => [],
        ]],
    ]);

    $response->assertOk()
        ->assertJsonPath('data.0.id', $workoutDayId)
        ->assertJsonPath('data.0.blocks.0.id', $blockId)
        ->assertJsonPath('data.0.blocks.0.exercises.0.id', $exerciseId)
        ->assertJsonPath('data.0.blocks.0.exercises.0.exercise_results.0.id', $resultId);

    $this->assertDatabaseHas('workout_days', ['id' => $workoutDayId, 'user_id' => $user->id]);
    $this->assertDatabaseHas('workout_blocks', ['id' => $blockId, 'workout_day_id' => $workoutDayId]);
    $this->assertDatabaseHas('planned_exercises', ['id' => $exerciseId, 'workout_day_id' => $workoutDayId, 'workout_block_id' => $blockId]);
    $this->assertDatabaseHas('exercise_results', ['id' => $resultId, 'planned_exercise_id' => $exerciseId]);
});
