<?php

use App\Contracts\GoogleAuthentication;
use App\Models\User;
use App\Services\Google\Auth\GoogleAuthenticationResult;
use Mockery\MockInterface;

use function Pest\Laravel\mock;

test('it authenticates an existing Google user and returns a bearer token', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);
    $result = new GoogleAuthenticationResult($user, 'google-token');

    mock(GoogleAuthentication::class, function (MockInterface $mock) use ($result): void {
        $mock->shouldReceive('authenticate')
            ->once()
            ->with('google-id-token', 'Jane’s iPhone')
            ->andReturn($result);
    });

    $response = $this->postJson('/api/auth/google', [
        'id_token' => 'google-id-token',
        'device_name' => 'Jane’s iPhone',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('token', 'google-token')
        ->assertJsonPath('token_type', 'Bearer');
});

test('it returns 422 when the Google authentication payload is invalid', function () {
    $response = $this->postJson('/api/auth/google', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['id_token', 'device_name']);
});

test('it returns 422 when initial registration data is sent to Google login', function () {
    $this->postJson('/api/auth/google', [
        'id_token' => 'google-id-token',
        'device_name' => 'Jane’s iPhone',
        'initial_data' => ['user' => ['id' => '7c9e6679-7425-40de-944b-e07fc1f90ae7']],
    ])->assertUnprocessable()->assertJsonValidationErrors(['initial_data']);
});
