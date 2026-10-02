<?php

use App\Enums\CoachingEnrollmentStatus;
use App\Models\BodyProgressPhotoSession;
use App\Models\CoachingEnrollment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('it stores a front progress photo on the default private disk', function () {
    Storage::fake(config('filesystems.default'));
    $user = User::factory()->create();
    $session = new BodyProgressPhotoSession;
    $session->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
    ])->save();

    $response = $this->actingAs($user, 'sanctum')->post("/api/body-progress-photo-sessions/{$session->id}/photos/front", [
        'image' => UploadedFile::fake()->image('front.jpg'),
    ]);

    $path = "body-progress-photos/{$session->id}-front.jpg";

    $response->assertOk()
        ->assertJsonPath('data.path', $path);

    Storage::disk(config('filesystems.default'))->assertExists($path);
    expect($session->fresh()->front_photo_path)->toBe($path);
});

test('it returns 404 when uploading a progress photo for another user session', function () {
    Storage::fake(config('filesystems.default'));
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $session = new BodyProgressPhotoSession;
    $session->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $otherUser->id,
    ])->save();

    $response = $this->actingAs($user, 'sanctum')->post("/api/body-progress-photo-sessions/{$session->id}/photos/profile", [
        'image' => UploadedFile::fake()->image('profile.jpg'),
    ]);

    $response->assertNotFound();
    Storage::disk(config('filesystems.default'))->assertDirectoryEmpty('body-progress-photos');
});

test('it returns 401 when uploading a progress photo without a bearer token', function () {
    $sessionId = (string) Str::uuid();

    $this->post("/api/body-progress-photo-sessions/{$sessionId}/photos/front", [
        'image' => UploadedFile::fake()->image('front.jpg'),
    ])->assertUnauthorized();

});

test('it rejects a non-JPEG progress photo', function () {
    Storage::fake(config('filesystems.default'));
    $user = User::factory()->create();
    $session = new BodyProgressPhotoSession;
    $session->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
    ])->save();

    $this->actingAs($user, 'sanctum')->post("/api/body-progress-photo-sessions/{$session->id}/photos/front", [
        'image' => UploadedFile::fake()->image('front.png'),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['image']);

    Storage::disk(config('filesystems.default'))->assertDirectoryEmpty('body-progress-photos');
});

test('it returns a private progress photo to its owner', function () {
    Storage::fake(config('filesystems.default'));
    $user = User::factory()->create();
    $session = BodyProgressPhotoSession::factory()->for($user)->create([
        'front_photo_path' => "body-progress-photos/{$user->id}-front.jpg",
    ]);
    Storage::put($session->front_photo_path, UploadedFile::fake()->image('front.jpg')->get());

    $this->actingAs($user, 'sanctum')
        ->get("/api/body-progress-photo-sessions/{$session->id}/photos/front")
        ->assertOk()
        ->assertHeader('content-disposition', "inline; filename={$user->id}-front.jpg");
});

test('it returns a private progress photo to an active coach', function () {
    Storage::fake(config('filesystems.default'));
    $client = User::factory()->create();
    $coach = User::factory()->create();
    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);
    $session = BodyProgressPhotoSession::factory()->for($client)->create([
        'profile_photo_path' => 'body-progress-photos/client-profile.jpg',
    ]);
    Storage::put($session->profile_photo_path, UploadedFile::fake()->image('profile.jpg')->get());

    $this->actingAs($coach, 'sanctum')
        ->get("/api/body-progress-photo-sessions/{$session->id}/photos/profile")
        ->assertOk();
});

test('it returns a private progress photo to an administrator', function () {
    Storage::fake(config('filesystems.default'));
    $session = BodyProgressPhotoSession::factory()->for(User::factory())->create([
        'profile_photo_path' => 'body-progress-photos/admin-profile.jpg',
    ]);
    Storage::put($session->profile_photo_path, UploadedFile::fake()->image('profile.jpg')->get());

    $this->actingAs(User::factory()->admin()->create(), 'sanctum')
        ->get("/api/body-progress-photo-sessions/{$session->id}/photos/profile")
        ->assertOk();
});

test('it returns 404 when an unauthorized user requests a private progress photo', function () {
    Storage::fake(config('filesystems.default'));
    $user = User::factory()->create();
    $session = BodyProgressPhotoSession::factory()->for(User::factory())->create([
        'torso_photo_path' => 'body-progress-photos/private-torso.jpg',
    ]);
    Storage::put($session->torso_photo_path, UploadedFile::fake()->image('torso.jpg')->get());

    $this->actingAs($user, 'sanctum')
        ->get("/api/body-progress-photo-sessions/{$session->id}/photos/torso")
        ->assertNotFound();
});

test('it returns 401 when requesting a private progress photo without a bearer token', function () {
    $sessionId = (string) Str::uuid();

    $this->get("/api/body-progress-photo-sessions/{$sessionId}/photos/front")
        ->assertUnauthorized();
});
