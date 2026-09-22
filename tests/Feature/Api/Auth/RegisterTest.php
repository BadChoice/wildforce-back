<?php

use App\Models\User;

test('it registers a user and returns a bearer token for the device', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_name' => 'Jane’s iPhone',
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.name', 'Jane Doe')
        ->assertJsonPath('user.email', 'jane@example.com')
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token', 'token_type']);

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();
    $authenticatedResponse = $this->getJson('/api/user', [
        'Authorization' => 'Bearer '.$response->json('token'),
    ]);

    expect($user->id)->toBeUuid()
        ->and($response->json('token'))->toBeString()->not->toBeEmpty()
        ->and($user->tokens)->toHaveCount(1)
        ->and($user->tokens->sole()->name)->toBe('Jane’s iPhone');

    $authenticatedResponse->assertOk()
        ->assertJsonPath('id', $user->id);
});

test('it validates the registration payload', function () {
    $response = $this->postJson('/api/auth/register', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password', 'device_name']);

    $this->assertDatabaseCount('users', 0);
});
