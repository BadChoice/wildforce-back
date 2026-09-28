<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('it stores a PNG avatar in the public Supabase bucket', function () {
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

test('it returns 401 when uploading an avatar without a bearer token', function () {
    $this->post('/api/users/avatar', [
        'image' => UploadedFile::fake()->image('avatar.png'),
    ])->assertUnauthorized();
});

test('it rejects a non-PNG avatar', function () {
    Storage::fake('supabase');
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->post('/api/users/avatar', [
        'image' => UploadedFile::fake()->image('avatar.jpg'),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['image']);

    Storage::disk('supabase')->assertMissing("avatars/{$user->id}.png");
});
