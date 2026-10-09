<?php

use App\Enums\CoachingEnrollmentStatus;
use App\Models\CoachExerciseContent;
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

test('it pulls the content of the active coaches of the user', function () {
    $media = CoachExerciseContent::factory()->withImage()->create([
        'coach_user_id' => $this->coach->id,
        'exercise' => 'walking',
        'notes' => 'Keep a neutral spine.',
    ]);
    $deletedMedia = CoachExerciseContent::factory()->create(['coach_user_id' => $this->coach->id, 'exercise' => 'barbellBenchPress']);
    $deletedMedia->delete();
    $pausedCoach = User::factory()->create();
    CoachingEnrollment::factory()->create([
        'client_user_id' => $this->client->id,
        'coach_user_id' => $pausedCoach->id,
        'status' => CoachingEnrollmentStatus::Paused,
    ]);
    $pausedCoachMedia = CoachExerciseContent::factory()->create(['coach_user_id' => $pausedCoach->id]);
    $otherCoachMedia = CoachExerciseContent::factory()->create();
    Sanctum::actingAs($this->client);

    $response = $this->getJson('/api/sync/pull?resource=coach-exercise-content');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment([
            'id' => $media->id,
            'coach_user_id' => $this->coach->id,
            'exercise' => 'walking',
            'image_url' => Storage::disk((string) config('filesystems.public_storage_disc'))->url($media->image_path),
            'youtube_video_id' => 'dQw4w9WgXcQ',
            'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'notes' => 'Keep a neutral spine.',
            'deleted_at' => null,
        ])
        ->assertJsonFragment(['id' => $deletedMedia->id, 'deleted_at' => $deletedMedia->deleted_at->toISOString()])
        ->assertJsonMissing(['id' => $pausedCoachMedia->id])
        ->assertJsonMissing(['id' => $otherCoachMedia->id])
        ->assertJsonMissingPath('data.0.image_path');
});

test('it pulls coach content in a batch with the same payload', function () {
    $media = CoachExerciseContent::factory()->create(['coach_user_id' => $this->coach->id]);
    Sanctum::actingAs($this->client);

    $this->postJson('/api/sync/pull/batch', [
        'resources' => [['resource' => 'coach-exercise-content', 'updated_after' => null]],
    ])->assertOk()
        ->assertJsonPath('data.0.resource', 'coach-exercise-content')
        ->assertJsonPath('data.0.record.id', $media->id)
        ->assertJsonPath('data.0.record.image_url', null)
        ->assertJsonPath('data.0.record.youtube_video_id', 'dQw4w9WgXcQ');
});

test('clients cannot push coach content', function () {
    Sanctum::actingAs($this->client);

    $this->postJson('/api/sync/push', [
        'resource' => 'coach-exercise-content',
        'records' => [[
            'id' => (string) Str::uuid(),
            'coach_user_id' => $this->coach->id,
            'exercise' => 'walking',
            'youtube_video_id' => 'dQw4w9WgXcQ',
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ]],
    ])->assertUnprocessable()->assertJsonValidationErrors(['resource']);

    expect(CoachExerciseContent::query()->count())->toBe(0);
});
