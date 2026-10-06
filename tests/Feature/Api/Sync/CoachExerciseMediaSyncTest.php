<?php

use App\Enums\CoachingEnrollmentStatus;
use App\Models\CoachExerciseMedia;
use App\Models\CoachingEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake((string) config('filesystems.public_storage_disc'));
    $this->client = User::factory()->create();
    $this->coach = User::factory()->create();
    CoachingEnrollment::factory()->create([
        'client_user_id' => $this->client->id,
        'coach_user_id' => $this->coach->id,
    ]);
});

test('it pulls the media of the active coaches of the user', function () {
    $media = CoachExerciseMedia::factory()->withImage()->create([
        'coach_user_id' => $this->coach->id,
        'exercise' => 'walking',
    ]);
    $deletedMedia = CoachExerciseMedia::factory()->create(['coach_user_id' => $this->coach->id, 'exercise' => 'benchPress']);
    $deletedMedia->delete();
    $pausedCoach = User::factory()->create();
    CoachingEnrollment::factory()->create([
        'client_user_id' => $this->client->id,
        'coach_user_id' => $pausedCoach->id,
        'status' => CoachingEnrollmentStatus::Paused,
    ]);
    $pausedCoachMedia = CoachExerciseMedia::factory()->create(['coach_user_id' => $pausedCoach->id]);
    $otherCoachMedia = CoachExerciseMedia::factory()->create();
    Sanctum::actingAs($this->client);

    $response = $this->getJson('/api/sync/pull?resource=coach-exercise-media');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment([
            'id' => $media->id,
            'coach_user_id' => $this->coach->id,
            'exercise' => 'walking',
            'image_url' => Storage::disk((string) config('filesystems.public_storage_disc'))->url($media->image_path),
            'youtube_video_id' => 'dQw4w9WgXcQ',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'deleted_at' => null,
        ])
        ->assertJsonFragment(['id' => $deletedMedia->id, 'deleted_at' => $deletedMedia->deleted_at->toISOString()])
        ->assertJsonMissing(['id' => $pausedCoachMedia->id])
        ->assertJsonMissing(['id' => $otherCoachMedia->id])
        ->assertJsonMissingPath('data.0.image_path');
});

test('it pulls coach media in a batch with the same payload', function () {
    $media = CoachExerciseMedia::factory()->create(['coach_user_id' => $this->coach->id]);
    Sanctum::actingAs($this->client);

    $this->postJson('/api/sync/pull/batch', [
        'resources' => [['resource' => 'coach-exercise-media', 'updated_after' => null]],
    ])->assertOk()
        ->assertJsonPath('data.0.resource', 'coach-exercise-media')
        ->assertJsonPath('data.0.record.id', $media->id)
        ->assertJsonPath('data.0.record.image_url', null)
        ->assertJsonPath('data.0.record.youtube_video_id', 'dQw4w9WgXcQ');
});

test('clients cannot push coach media', function () {
    Sanctum::actingAs($this->client);

    $this->postJson('/api/sync/push', [
        'resource' => 'coach-exercise-media',
        'records' => [[
            'id' => (string) Str::uuid(),
            'coach_user_id' => $this->coach->id,
            'exercise' => 'walking',
            'youtube_video_id' => 'dQw4w9WgXcQ',
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ]],
    ])->assertUnprocessable()->assertJsonValidationErrors(['resource']);

    expect(CoachExerciseMedia::query()->count())->toBe(0);
});
