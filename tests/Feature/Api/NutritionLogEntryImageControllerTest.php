<?php

use App\Enums\CoachingEnrollmentStatus;
use App\Models\CoachingEnrollment;
use App\Models\NutritionLogEntry;
use App\Models\NutritionLogMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('it stores an image on a nutrition log entry and links it through local media', function () {
    Storage::fake(config('filesystems.default'));
    $user = User::factory()->create();
    $entry = NutritionLogEntry::factory()->for($user)->create();

    $response = $this->actingAs($user, 'sanctum')->post("/api/nutrition-log-entries/{$entry->id}/image", [
        'image' => UploadedFile::fake()->image('food.jpg'),
    ]);

    $path = "nutrition-log-entries/{$entry->id}.jpg";

    $response->assertOk()
        ->assertJsonPath('data.path', $path);

    Storage::disk(config('filesystems.default'))->assertExists($path);
    $this->assertDatabaseHas('nutrition_log_media', [
        'user_id' => $user->id,
        'source' => 'localPhoto',
        'local_relative_path' => $path,
        'remote_url' => null,
    ]);
    expect($entry->fresh()->media)->not->toBeNull()
        ->and($entry->fresh()->media->local_relative_path)->toBe($path);
});

test('it updates an existing local nutrition log media record', function () {
    Storage::fake(config('filesystems.default'));
    $user = User::factory()->create();
    $media = NutritionLogMedia::factory()->for($user)->create([
        'local_relative_path' => 'nutrition-log-entries/previous.jpg',
    ]);
    $entry = NutritionLogEntry::factory()->for($user)->create(['nutrition_log_media_id' => $media->id]);

    $this->actingAs($user, 'sanctum')->post("/api/nutrition-log-entries/{$entry->id}/image", [
        'image' => UploadedFile::fake()->image('food.jpg'),
    ])->assertOk();

    expect($entry->fresh()->nutrition_log_media_id)->toBe($media->id)
        ->and($media->fresh()->local_relative_path)->toBe("nutrition-log-entries/{$entry->id}.jpg");
});

test('it does not update local nutrition log media owned by another user', function () {
    Storage::fake(config('filesystems.default'));
    $user = User::factory()->create();
    $media = NutritionLogMedia::factory()->create([
        'local_relative_path' => 'nutrition-log-entries/other-user.jpg',
    ]);
    $entry = NutritionLogEntry::factory()->for($user)->create(['nutrition_log_media_id' => $media->id]);

    $this->actingAs($user, 'sanctum')->post("/api/nutrition-log-entries/{$entry->id}/image", [
        'image' => UploadedFile::fake()->image('food.jpg'),
    ])->assertOk();

    expect($entry->fresh()->nutrition_log_media_id)->not->toBe($media->id)
        ->and($media->fresh()->local_relative_path)->toBe('nutrition-log-entries/other-user.jpg')
        ->and($entry->fresh()->media->user_id)->toBe($user->id);
});

test('it preserves existing remote media when uploading a local nutrition log image', function () {
    Storage::fake(config('filesystems.default'));
    $user = User::factory()->create();
    $remoteMedia = NutritionLogMedia::factory()->for($user)->create([
        'source' => 'remoteUrl',
        'remote_url' => 'https://example.com/food.jpg',
    ]);
    $entry = NutritionLogEntry::factory()->for($user)->create(['nutrition_log_media_id' => $remoteMedia->id]);

    $this->actingAs($user, 'sanctum')->post("/api/nutrition-log-entries/{$entry->id}/image", [
        'image' => UploadedFile::fake()->image('food.jpg'),
    ])->assertOk();

    expect($entry->fresh()->nutrition_log_media_id)->not->toBe($remoteMedia->id)
        ->and($remoteMedia->fresh()->remote_url)->toBe('https://example.com/food.jpg');
});

test('it returns 404 when uploading an image for another user nutrition log entry', function () {
    Storage::fake(config('filesystems.default'));
    $user = User::factory()->create();
    $entry = NutritionLogEntry::factory()->for(User::factory())->create();

    $this->actingAs($user, 'sanctum')->post("/api/nutrition-log-entries/{$entry->id}/image", [
        'image' => UploadedFile::fake()->image('food.jpg'),
    ])->assertNotFound();

    Storage::disk(config('filesystems.default'))->assertDirectoryEmpty('nutrition-log-entries');
});

test('it returns 401 when uploading a nutrition log entry image without a bearer token', function () {
    $entryId = (string) Str::uuid();

    $this->post("/api/nutrition-log-entries/{$entryId}/image", [
        'image' => UploadedFile::fake()->image('food.jpg'),
    ])->assertUnauthorized();
});

test('it rejects a non-JPEG image for a nutrition log entry', function () {
    Storage::fake(config('filesystems.default'));
    $entry = NutritionLogEntry::factory()->create();

    $this->actingAs($entry->user, 'sanctum')->post("/api/nutrition-log-entries/{$entry->id}/image", [
        'image' => UploadedFile::fake()->image('food.png'),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['image']);

    Storage::disk(config('filesystems.default'))->assertDirectoryEmpty('nutrition-log-entries');
});

test('it returns a private nutrition log image to its owner', function () {
    Storage::fake(config('filesystems.default'));
    $user = User::factory()->create();
    $entry = nutritionLogEntryWithImage($user);

    $this->actingAs($user, 'sanctum')
        ->get("/api/nutrition-log-entries/{$entry->id}/image")
        ->assertOk()
        ->assertHeader('content-disposition', "inline; filename={$entry->id}.jpg");
});

test('it returns a private nutrition log image to an active coach', function () {
    Storage::fake(config('filesystems.default'));
    $client = User::factory()->create();
    $coach = User::factory()->create();
    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);
    $entry = nutritionLogEntryWithImage($client);

    $this->actingAs($coach, 'sanctum')
        ->get("/api/nutrition-log-entries/{$entry->id}/image")
        ->assertOk();
});

test('it returns a private nutrition log image to an administrator', function () {
    Storage::fake(config('filesystems.default'));
    $entry = nutritionLogEntryWithImage(User::factory()->create());

    $this->actingAs(User::factory()->admin()->create(), 'sanctum')
        ->get("/api/nutrition-log-entries/{$entry->id}/image")
        ->assertOk();
});

test('it returns 404 when an unauthorized user requests a private nutrition log image', function () {
    Storage::fake(config('filesystems.default'));
    $entry = nutritionLogEntryWithImage(User::factory()->create());

    $this->actingAs(User::factory()->create(), 'sanctum')
        ->get("/api/nutrition-log-entries/{$entry->id}/image")
        ->assertNotFound();
});

test('it returns 401 when requesting a private nutrition log image without a bearer token', function () {
    $entryId = (string) Str::uuid();

    $this->get("/api/nutrition-log-entries/{$entryId}/image")
        ->assertUnauthorized();
});

test('it returns a private nutrition log image to an active coach in the backend', function () {
    Storage::fake(config('filesystems.default'));
    $client = User::factory()->create();
    $coach = User::factory()->create();
    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);
    $entry = nutritionLogEntryWithImage($client);

    $this->actingAs($coach)
        ->get(route('nutrition-log-entries.image', $entry))
        ->assertOk();
});

test('it returns 404 when an unauthorized user requests a private nutrition log image in the backend', function () {
    Storage::fake(config('filesystems.default'));
    $entry = nutritionLogEntryWithImage(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->get(route('nutrition-log-entries.image', $entry))
        ->assertNotFound();
});

function nutritionLogEntryWithImage(User $user): NutritionLogEntry
{
    $entry = NutritionLogEntry::factory()->for($user)->create();
    $path = "nutrition-log-entries/{$entry->id}.jpg";
    $media = NutritionLogMedia::factory()->create(['local_relative_path' => $path]);
    $entry->media()->associate($media);
    $entry->save();
    Storage::put($path, UploadedFile::fake()->image('food.jpg')->get());

    return $entry;
}
