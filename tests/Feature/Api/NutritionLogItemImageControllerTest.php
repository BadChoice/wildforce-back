<?php

use App\Models\NutritionLogEntry;
use App\Models\NutritionLogItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('it stores an image for a nutrition log item on the default private disk', function () {
    Storage::fake(config('filesystems.default'));
    $user = User::factory()->create();
    $entry = NutritionLogEntry::factory()->create(['user_id' => $user->id]);
    $item = NutritionLogItem::factory()->create(['nutrition_log_entry_id' => $entry->id]);

    $response = $this->actingAs($user, 'sanctum')->post("/api/nutrition-log-items/{$item->id}/image", [
        'image' => UploadedFile::fake()->image('food.jpg'),
    ]);

    $path = "nutrition-log-items/{$item->id}.jpg";

    $response->assertOk()
        ->assertJsonPath('data.path', $path);

    Storage::disk(config('filesystems.default'))->assertExists($path);
    expect($item->fresh()->image_path)->toBe($path);
});

test('it returns 404 when uploading an image for another user nutrition log item', function () {
    Storage::fake(config('filesystems.default'));
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $entry = NutritionLogEntry::factory()->create(['user_id' => $otherUser->id]);
    $item = NutritionLogItem::factory()->create(['nutrition_log_entry_id' => $entry->id]);

    $response = $this->actingAs($user, 'sanctum')->post("/api/nutrition-log-items/{$item->id}/image", [
        'image' => UploadedFile::fake()->image('food.jpg'),
    ]);

    $response->assertNotFound();
    Storage::disk(config('filesystems.default'))->assertDirectoryEmpty('nutrition-log-items');
});

test('it returns 401 when uploading a nutrition log item image without a bearer token', function () {
    $itemId = (string) Str::uuid();

    $this->post("/api/nutrition-log-items/{$itemId}/image", [
        'image' => UploadedFile::fake()->image('food.jpg'),
    ])->assertUnauthorized();
});

test('it rejects a non-JPEG image for nutrition log item', function () {
    Storage::fake(config('filesystems.default'));
    $user = User::factory()->create();
    $entry = NutritionLogEntry::factory()->create(['user_id' => $user->id]);
    $item = NutritionLogItem::factory()->create(['nutrition_log_entry_id' => $entry->id]);

    $this->actingAs($user, 'sanctum')->post("/api/nutrition-log-items/{$item->id}/image", [
        'image' => UploadedFile::fake()->image('food.png'),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['image']);

    Storage::disk(config('filesystems.default'))->assertDirectoryEmpty('nutrition-log-items');
});
