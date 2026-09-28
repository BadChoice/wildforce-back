<?php

use App\Contracts\AppleAuthentication;
use App\Models\User;
use App\Services\Apple\Auth\AppleAuthenticationResult;
use Mockery\MockInterface;

use function Pest\Laravel\mock;

test('it authenticates an existing Apple user and returns a bearer token', function () {
    $user = User::factory()->create(['email' => 'jane@example.com']);
    $result = new AppleAuthenticationResult($user, 'apple-token');

    mock(AppleAuthentication::class, function (MockInterface $mock) use ($result): void {
        $mock->shouldReceive('authenticate')
            ->once()
            ->with('apple-authorization-code', 'Jane’s iPhone', 'Jane Doe')
            ->andReturn($result);
    });

    $response = $this->postJson('/api/auth/apple', [
        'authorization_code' => 'apple-authorization-code',
        'device_name' => 'Jane’s iPhone',
        'full_name' => 'Jane Doe',
    ]);

    $response->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('token', 'apple-token')
        ->assertJsonPath('token_type', 'Bearer');
});

test('it returns 422 when the Apple authentication payload is invalid', function () {
    $response = $this->postJson('/api/auth/apple', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['authorization_code', 'device_name']);
});
