<?php

use App\Models\TrainingLocation;
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
        ->assertJsonFragment(['id' => $location->id, 'deleted_at' => null])
        ->assertJsonFragment(['id' => $deletedLocation->id])
        ->assertJsonMissing(['id' => $otherLocation->id]);

    expect($response->json('data.1.deleted_at'))->not->toBeNull();
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
