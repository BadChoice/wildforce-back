<?php

namespace App\Services\Google\Auth;

use App\Contracts\GoogleAuthentication;
use App\Models\User;
use App\Models\UserIdentity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class GoogleAuthenticator implements GoogleAuthentication
{
    public function __construct(private GoogleIdentityTokenVerifier $googleIdentityTokenVerifier) {}

    public function authenticate(string $identityToken, string $deviceName): GoogleAuthenticationResult
    {
        $result = $this->resolve($identityToken);
        $token = $result->user->createToken($deviceName);

        return new GoogleAuthenticationResult($result->user, $token->plainTextToken, $result->trialEndsAt);
    }

    public function resolve(string $identityToken): GoogleUserAuthenticationResult
    {
        $claims = $this->googleIdentityTokenVerifier->verify($identityToken);
        $providerUserId = $this->providerUserId($claims);
        $identity = $this->googleIdentityQuery()->with('user.subscription')->where('provider_user_id', $providerUserId)->first();

        if ($identity !== null) {
            return new GoogleUserAuthenticationResult($identity->user);
        }

        throw ValidationException::withMessages([
            'id_token' => ['No account is linked to this Google ID. Complete registration first.'],
        ]);
    }

    public function link(User $user, string $identityToken): UserIdentity
    {
        $claims = $this->googleIdentityTokenVerifier->verify($identityToken);
        $providerUserId = $this->providerUserId($claims);
        $identity = $this->googleIdentityQuery()->where('provider_user_id', $providerUserId)->first();

        if ($identity !== null) {
            if ($identity->user_id === $user->id) {
                return $identity;
            }

            throw new ConflictHttpException('This Google account is already linked to another user.');
        }

        return $this->createGoogleIdentity($user, $providerUserId, $claims);
    }

    public function unlink(User $user): void
    {
        $identity = $this->googleIdentityQuery()->whereBelongsTo($user)->first();

        if ($identity === null) {
            return;
        }

        if ($user->password === null && $user->identities()->count() === 1) {
            throw ValidationException::withMessages(['provider' => ['Set a password before removing your only sign-in method.']]);
        }

        $identity->delete();
    }

    /** @param array<string, mixed> $claims */
    private function createGoogleIdentity(User $user, string $providerUserId, array $claims): UserIdentity
    {
        return UserIdentity::create([
            'user_id' => $user->id,
            'provider' => UserIdentity::GoogleProvider,
            'provider_user_id' => $providerUserId,
            'provider_email' => is_string($claims['email'] ?? null) ? Str::lower($claims['email']) : null,
        ]);
    }

    /** @param array<string, mixed> $claims */
    private function providerUserId(array $claims): string
    {
        $providerUserId = $claims['sub'] ?? null;

        if (! is_string($providerUserId) || $providerUserId === '') {
            throw ValidationException::withMessages(['id_token' => ['Google did not return a user identifier.']]);
        }

        return $providerUserId;
    }

    /** @return Builder<UserIdentity> */
    private function googleIdentityQuery(): Builder
    {
        return UserIdentity::query()->where('provider', UserIdentity::GoogleProvider);
    }
}
