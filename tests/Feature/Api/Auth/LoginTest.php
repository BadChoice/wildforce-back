<?php

use App\Models\User;

test('it logs in a user and issues a new token for the device', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'password123',
        'device_name' => 'Jane’s reinstalled iPhone',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token', 'token_type']);

    expect($response->json('token'))->toBeString()->not->toBeEmpty()
        ->and($user->fresh()->tokens)->toHaveCount(1)
        ->and($user->fresh()->tokens->sole()->name)->toBe('Jane’s reinstalled iPhone');
});

test('it rejects invalid credentials without creating a token', function () {
    $user = User::factory()->create([
        'email' => 'jane@example.com',
        'password' => 'password123',
    ]);

    $response = $this->postJson('/api/auth/login', [
        'email' => 'jane@example.com',
        'password' => 'incorrect-password',
        'device_name' => 'Jane’s iPhone',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);

    expect($user->fresh()->tokens)->toHaveCount(0);
});
