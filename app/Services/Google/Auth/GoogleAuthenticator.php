<?php

namespace App\Services\Google\Auth;

use App\Contracts\GoogleAuthentication;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserIdentity;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
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

        $email = $this->verifiedEmail($claims);

        if (User::query()->where('email', $email)->exists()) {
            throw new ConflictHttpException('This email already has an account. Sign in with an existing method before linking Google.');
        }

        return DB::transaction(function () use ($email, $providerUserId, $claims): GoogleUserAuthenticationResult {
            $user = User::create([
                'name' => $this->nameForNewUser($claims, $email),
                'email' => $email,
                'email_verified_at' => now(),
                'password' => null,
            ]);
            $trial = $user->attachSubscription(Subscription::createTrial());
            $this->createGoogleIdentity($user, $providerUserId, $claims);
            $trialEndsAt = $trial->getAttribute('renews_at');

            if (! $trialEndsAt instanceof CarbonInterface) {
                throw new RuntimeException('The trial end date is invalid.');
            }

            return new GoogleUserAuthenticationResult($user, $trialEndsAt);
        });
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

    /** @param array<string, mixed> $claims */
    private function verifiedEmail(array $claims): string
    {
        $email = $claims['email'] ?? null;
        $isVerified = ($claims['email_verified'] ?? null) === true || ($claims['email_verified'] ?? null) === 'true';

        if (! $isVerified || ! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['id_token' => ['Google did not return a verified email address for this account.']]);
        }

        return Str::lower($email);
    }

    /** @param array<string, mixed> $claims */
    private function nameForNewUser(array $claims, string $email): string
    {
        $name = $claims['name'] ?? null;

        if (is_string($name)) {
            $name = trim((string) preg_replace('/\s+/', ' ', $name));

            if ($name !== '') {
                return $name;
            }
        }

        return Str::before($email, '@');
    }

    /** @return Builder<UserIdentity> */
    private function googleIdentityQuery(): Builder
    {
        return UserIdentity::query()->where('provider', UserIdentity::GoogleProvider);
    }
}
