<?php

use App\Models\User;
use App\Models\UserIdentity;
use App\Services\Apple\Auth\AppleAuthenticator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

test('it rejects an unlinked Apple user after validating Apple’s signed identity token', function () {
    $clientPrivateKey = appleTestClientPrivateKey();
    [$identityPrivateKey, $jwk] = appleTestIdentityKeyMaterial();
    $identityToken = appleTestIdentityToken($identityPrivateKey, [
        'iss' => 'https://appleid.apple.com',
        'aud' => 'com.wildforce.app',
        'exp' => now()->addMinute()->timestamp,
        'sub' => 'apple-user-123',
        'email' => 'jane@privaterelay.appleid.com',
        'email_verified' => 'true',
    ]);

    config()->set('services.apple', [
        'client_id' => 'com.wildforce.app',
        'team_id' => 'TEAM123456',
        'key_id' => 'KEY1234567',
        'private_key' => $clientPrivateKey,
    ]);
    Cache::forget('apple-sign-in-public-keys');
    Http::preventStrayRequests();
    Http::fake([
        'https://appleid.apple.com/auth/token' => Http::response(['id_token' => $identityToken]),
        'https://appleid.apple.com/auth/keys' => Http::response(['keys' => [$jwk]]),
    ]);

    expect(fn () => app(AppleAuthenticator::class)->authenticate('apple-authorization-code', 'Jane’s iPhone', 'Jane Doe'))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('user_identities', 0);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://appleid.apple.com/auth/token');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://appleid.apple.com/auth/keys');
});

test('it does not remove an Apple identity that is the user’s only sign-in method', function () {
    $user = User::factory()->create(['password' => null]);
    UserIdentity::create([
        'user_id' => $user->id,
        'provider' => UserIdentity::AppleProvider,
        'provider_user_id' => 'apple-user-123',
    ]);

    expect(fn () => app(AppleAuthenticator::class)->unlink($user))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('user_identities', 1);
});

test('it removes Apple when the user has another linked identity', function () {
    $user = User::factory()->create(['password' => null]);
    $appleIdentity = UserIdentity::create([
        'user_id' => $user->id,
        'provider' => UserIdentity::AppleProvider,
        'provider_user_id' => 'apple-user-123',
    ]);
    $googleIdentity = new UserIdentity([
        'user_id' => $user->id,
        'provider_user_id' => 'google-user-123',
    ]);
    $googleIdentity->provider = 'google';
    $googleIdentity->save();

    app(AppleAuthenticator::class)->unlink($user);

    $this->assertModelMissing($appleIdentity);
    $this->assertDatabaseHas('user_identities', [
        'user_id' => $user->id,
        'provider' => 'google',
    ]);
});

/**
 * @return array{string, array<string, string>}
 */
function appleTestClientPrivateKey(): string
{
    $key = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
    ]);
    openssl_pkey_export($key, $privateKey);

    return $privateKey;
}

/**
 * @return array{string, array<string, string>}
 */
function appleTestIdentityKeyMaterial(): array
{
    $key = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
        'private_key_bits' => 2048,
    ]);
    openssl_pkey_export($key, $privateKey);
    $details = openssl_pkey_get_details($key);

    return [$privateKey, [
        'kty' => 'RSA',
        'alg' => 'RS256',
        'kid' => 'apple-test-key',
        'n' => appleTestBase64UrlEncode($details['rsa']['n']),
        'e' => appleTestBase64UrlEncode($details['rsa']['e']),
    ]];
}

/**
 * @param  array<string, int|string>  $claims
 */
function appleTestIdentityToken(string $privateKey, array $claims): string
{
    $header = appleTestBase64UrlEncode(json_encode(['alg' => 'RS256', 'kid' => 'apple-test-key'], JSON_THROW_ON_ERROR));
    $payload = appleTestBase64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
    openssl_sign($header.'.'.$payload, $signature, $privateKey, OPENSSL_ALGO_SHA256);

    return $header.'.'.$payload.'.'.appleTestBase64UrlEncode($signature);
}

function appleTestBase64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}
