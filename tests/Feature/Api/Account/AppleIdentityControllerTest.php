<?php

use App\Contracts\AppleAuthentication;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Support\Collection;
use Mockery\MockInterface;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;

test('it lists only the authenticated user’s linked identities', function () {
    $user = User::factory()->create();
    $identity = UserIdentity::create([
        'user_id' => $user->id,
        'provider' => UserIdentity::AppleProvider,
        'provider_user_id' => 'apple-user-123',
        'provider_email' => 'jane@privaterelay.appleid.com',
    ]);

    mock(AppleAuthentication::class, function (MockInterface $mock) use ($user, $identity): void {
        $mock->shouldReceive('identities')
            ->once()
            ->withArgs(fn (User $authenticatedUser): bool => $authenticatedUser->is($user))
            ->andReturn(new Collection([$identity]));
    });

    $response = actingAs($user, 'sanctum')->getJson('/api/account/identities');

    $response->assertOk()
        ->assertJsonPath('data.0.provider', 'apple')
        ->assertJsonPath('data.0.email', 'jane@privaterelay.appleid.com')
        ->assertJsonStructure(['data' => [['provider', 'email', 'linked_at']]]);
});

test('it links Apple to the authenticated user', function () {
    $user = User::factory()->create();
    $identity = UserIdentity::create([
        'user_id' => $user->id,
        'provider' => UserIdentity::AppleProvider,
        'provider_user_id' => 'apple-user-123',
        'provider_email' => 'jane@privaterelay.appleid.com',
    ]);

    mock(AppleAuthentication::class, function (MockInterface $mock) use ($user, $identity): void {
        $mock->shouldReceive('link')
            ->once()
            ->withArgs(fn (User $authenticatedUser, string $code): bool => $authenticatedUser->is($user) && $code === 'new-apple-code')
            ->andReturn($identity);
    });

    $response = actingAs($user, 'sanctum')->postJson('/api/account/identities/apple', [
        'authorization_code' => 'new-apple-code',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.provider', 'apple')
        ->assertJsonPath('data.email', 'jane@privaterelay.appleid.com');

    $this->assertDatabaseHas('user_identities', [
        'id' => $identity->id,
        'user_id' => $user->id,
        'provider' => 'apple',
    ]);
});

test('it removes Apple from the authenticated user', function () {
    $user = User::factory()->create();

    mock(AppleAuthentication::class, function (MockInterface $mock) use ($user): void {
        $mock->shouldReceive('unlink')
            ->once()
            ->withArgs(fn (User $authenticatedUser): bool => $authenticatedUser->is($user));
    });

    $response = actingAs($user, 'sanctum')->deleteJson('/api/account/identities/apple');

    $response->assertNoContent();
});

test('it returns 401 when listing identities without a bearer token', function () {
    $response = $this->getJson('/api/account/identities');

    $response->assertUnauthorized();
});
