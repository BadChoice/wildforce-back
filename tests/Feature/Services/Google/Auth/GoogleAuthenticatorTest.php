<?php

use App\Models\User;
use App\Models\UserIdentity;
use App\Services\Google\Auth\GoogleAuthenticator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

test('it creates a Google user after validating Google’s signed identity token', function () {
    [$identityPrivateKey, $jwk] = googleTestIdentityKeyMaterial();
    $identityToken = googleTestIdentityToken($identityPrivateKey, [
        'iss' => 'https://accounts.google.com',
        'aud' => 'google-client-id.apps.googleusercontent.com',
        'exp' => now()->addMinute()->timestamp,
        'sub' => 'google-user-123',
        'email' => 'jane@example.com',
        'email_verified' => true,
        'name' => 'Jane Doe',
    ]);

    config()->set('services.google.client_id', 'google-client-id.apps.googleusercontent.com');
    Cache::forget('google-sign-in-public-keys');
    Http::preventStrayRequests();
    Http::fake([
        'https://www.googleapis.com/oauth2/v3/certs' => Http::response(['keys' => [$jwk]]),
    ]);

    $result = app(GoogleAuthenticator::class)->authenticate($identityToken, 'Jane’s iPhone');

    expect($result->user->name)->toBe('Jane Doe')
        ->and($result->user->email)->toBe('jane@example.com')
        ->and($result->user->password)->toBeNull()
        ->and($result->token)->toBeString()->not->toBeEmpty()
        ->and($result->trialEndsAt)->not->toBeNull();

    $this->assertDatabaseHas('user_identities', [
        'user_id' => $result->user->id,
        'provider' => 'google',
        'provider_user_id' => 'google-user-123',
        'provider_email' => 'jane@example.com',
    ]);
    $this->assertDatabaseCount('user_identities', 1);

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://www.googleapis.com/oauth2/v3/certs');
});

test('it rejects a Google identity token issued for another client', function () {
    [$identityPrivateKey, $jwk] = googleTestIdentityKeyMaterial();
    $identityToken = googleTestIdentityToken($identityPrivateKey, [
        'iss' => 'accounts.google.com',
        'aud' => 'another-client.apps.googleusercontent.com',
        'exp' => now()->addMinute()->timestamp,
        'sub' => 'google-user-123',
        'email' => 'jane@example.com',
        'email_verified' => true,
    ]);

    config()->set('services.google.client_id', 'google-client-id.apps.googleusercontent.com');
    Cache::forget('google-sign-in-public-keys');
    Http::preventStrayRequests();
    Http::fake([
        'https://www.googleapis.com/oauth2/v3/certs' => Http::response(['keys' => [$jwk]]),
    ]);

    expect(fn () => app(GoogleAuthenticator::class)->authenticate($identityToken, 'Jane’s iPhone'))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('user_identities', 0);
});

test('it accepts a Google identity token issued for the web client', function () {
    [$identityPrivateKey, $jwk] = googleTestIdentityKeyMaterial();
    $identityToken = googleTestIdentityToken($identityPrivateKey, [
        'iss' => 'accounts.google.com',
        'aud' => 'web-client-id.apps.googleusercontent.com',
        'exp' => now()->addMinute()->timestamp,
        'sub' => 'google-user-123',
        'email' => 'jane@example.com',
        'email_verified' => true,
    ]);

    config()->set('services.google.client_id', 'mobile-client-id.apps.googleusercontent.com');
    config()->set('services.google.web_client_id', 'web-client-id.apps.googleusercontent.com');
    Cache::forget('google-sign-in-public-keys');
    Http::preventStrayRequests();
    Http::fake([
        'https://www.googleapis.com/oauth2/v3/certs' => Http::response(['keys' => [$jwk]]),
    ]);

    $result = app(GoogleAuthenticator::class)->resolve($identityToken);

    expect($result->user->email)->toBe('jane@example.com');
});

test('it does not remove a Google identity that is the user’s only sign-in method', function () {
    $user = User::factory()->create(['password' => null]);
    UserIdentity::create([
        'user_id' => $user->id,
        'provider' => UserIdentity::GoogleProvider,
        'provider_user_id' => 'google-user-123',
    ]);

    expect(fn () => app(GoogleAuthenticator::class)->unlink($user))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('user_identities', 1);
});

/**
 * @return array{string, array<string, string>}
 */
function googleTestIdentityKeyMaterial(): array
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
        'kid' => 'google-test-key',
        'n' => googleTestBase64UrlEncode($details['rsa']['n']),
        'e' => googleTestBase64UrlEncode($details['rsa']['e']),
    ]];
}

/**
 * @param  array<string, bool|int|string>  $claims
 */
function googleTestIdentityToken(string $privateKey, array $claims): string
{
    $header = googleTestBase64UrlEncode(json_encode(['alg' => 'RS256', 'kid' => 'google-test-key'], JSON_THROW_ON_ERROR));
    $payload = googleTestBase64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
    openssl_sign($header.'.'.$payload, $signature, $privateKey, OPENSSL_ALGO_SHA256);

    return $header.'.'.$payload.'.'.googleTestBase64UrlEncode($signature);
}

function googleTestBase64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}
