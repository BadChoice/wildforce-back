<?php

use App\Contracts\GoogleAuthentication;
use App\Models\User;
use App\Models\UserIdentity;
use Mockery\MockInterface;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

test('it links Google to the authenticated user', function () {
    $user = User::factory()->create();
    $identity = UserIdentity::create([
        'user_id' => $user->id,
        'provider' => UserIdentity::GoogleProvider,
        'provider_user_id' => 'google-user-123',
        'provider_email' => 'jane@example.com',
    ]);

    mock(GoogleAuthentication::class, function (MockInterface $mock) use ($user, $identity): void {
        $mock->shouldReceive('link')
            ->once()
            ->withArgs(fn (User $authenticatedUser, string $token): bool => $authenticatedUser->is($user) && $token === 'google-id-token')
            ->andReturn($identity);
    });

    $response = actingAs($user, 'sanctum')->postJson('/api/account/identities/google', [
        'id_token' => 'google-id-token',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.provider', 'google')
        ->assertJsonPath('data.email', 'jane@example.com');

    $this->assertModelExists($identity);
});

test('it removes Google from the authenticated user', function () {
    $user = User::factory()->create();

    mock(GoogleAuthentication::class, function (MockInterface $mock) use ($user): void {
        $mock->shouldReceive('unlink')
            ->once()
            ->withArgs(fn (User $authenticatedUser): bool => $authenticatedUser->is($user));
    });

    $response = actingAs($user, 'sanctum')->deleteJson('/api/account/identities/google');

    $response->assertNoContent();
});

test('it returns 401 when linking Google without a bearer token', function () {
    $response = $this->postJson('/api/account/identities/google', [
        'id_token' => 'google-id-token',
    ]);

    $response->assertUnauthorized();
});
