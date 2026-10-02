<?php

use App\Models\BodyProgressPhotoSession;
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
