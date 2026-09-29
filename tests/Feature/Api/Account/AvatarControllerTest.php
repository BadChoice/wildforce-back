<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('it stores a PNG avatar in the configured production disk', function () {
    config()->set('filesystems.public_storage_disc', 'supabase');
    Storage::fake('supabase');
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->post('/api/users/avatar', [
        'image' => UploadedFile::fake()->image('avatar.png'),
    ]);

    $path = "avatars/{$user->id}.png";

    $response->assertOk()
        ->assertJsonPath('data.path', $path)
        ->assertJsonPath('data.url', Storage::disk('supabase')->url($path));

    Storage::disk('supabase')->assertExists($path);
});

test('it stores a PNG avatar in the local public disk when configured', function () {
    config()->set('filesystems.public_storage_disc', 'public');
    Storage::fake('public');
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->post('/api/users/avatar', [
        'image' => UploadedFile::fake()->image('avatar.png'),
    ]);

    $path = "avatars/{$user->id}.png";

    $response->assertOk()
        ->assertJsonPath('data.path', $path)
        ->assertJsonPath('data.url', Storage::disk('public')->url($path));

    Storage::disk('public')->assertExists($path);
});

test('it returns 401 when uploading an avatar without a bearer token', function () {
    $this->post('/api/users/avatar', [
        'image' => UploadedFile::fake()->image('avatar.png'),
    ])->assertUnauthorized();
});

test('it rejects a non-PNG avatar', function () {
    config()->set('filesystems.public_storage_disc', 'supabase');
    Storage::fake('supabase');
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->post('/api/users/avatar', [
        'image' => UploadedFile::fake()->image('avatar.jpg'),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['image']);

    Storage::disk('supabase')->assertMissing("avatars/{$user->id}.png");
});
