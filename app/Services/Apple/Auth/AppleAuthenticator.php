<?php

namespace App\Services\Apple\Auth;

use App\Contracts\AppleAuthentication;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserIdentity;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class AppleAuthenticator implements AppleAuthentication
{
    private const TokenEndpoint = 'https://appleid.apple.com/auth/token';

    public function __construct(
        private AppleClientSecret $appleClientSecret,
        private AppleIdentityTokenVerifier $appleIdentityTokenVerifier,
    ) {}

    public function authenticate(string $authorizationCode, string $deviceName, ?string $fullName): AppleAuthenticationResult
    {
        $claims = $this->claimsForAuthorizationCode($authorizationCode);
        $providerUserId = $this->providerUserId($claims);
        $identity = $this->appleIdentityQuery()->with('user.subscription')->where('provider_user_id', $providerUserId)->first();

        if ($identity !== null) {
            $token = $identity->user->createToken($deviceName);

            return new AppleAuthenticationResult($identity->user, $token->plainTextToken);
        }

        $email = $this->verifiedEmail($claims);

        if (User::query()->where('email', $email)->exists()) {
            throw new ConflictHttpException('This email already has an account. Sign in with an existing method before linking Apple.');
        }

        return DB::transaction(function () use ($email, $providerUserId, $claims, $deviceName, $fullName): AppleAuthenticationResult {
            $user = User::create([
                'name' => $this->nameForNewUser($fullName, $email),
                'email' => $email,
                'email_verified_at' => now(),
                'password' => null,
            ]);
            $trial = $user->attachSubscription(Subscription::createTrial());
            $this->createAppleIdentity($user, $providerUserId, $claims);
            $token = $user->createToken($deviceName);
            $trialEndsAt = $trial->getAttribute('renews_at');

            if (! $trialEndsAt instanceof CarbonInterface) {
                throw new RuntimeException('The trial end date is invalid.');
            }

            return new AppleAuthenticationResult($user, $token->plainTextToken, $trialEndsAt);
        });
    }

    public function link(User $user, string $authorizationCode): UserIdentity
    {
        $claims = $this->claimsForAuthorizationCode($authorizationCode);
        $providerUserId = $this->providerUserId($claims);
        $identity = $this->appleIdentityQuery()->where('provider_user_id', $providerUserId)->first();

        if ($identity !== null) {
            if ($identity->user_id === $user->id) {
                return $identity;
            }

            throw new ConflictHttpException('This Apple account is already linked to another user.');
        }

        return $this->createAppleIdentity($user, $providerUserId, $claims);
    }

    /** @return Collection<int, UserIdentity> */
    public function identities(User $user): Collection
    {
        return $user->identities()->orderBy('created_at')->get();
    }

    public function unlink(User $user): void
    {
        $identity = $this->appleIdentityQuery()->whereBelongsTo($user)->first();

        if ($identity === null) {
            return;
        }

        if ($user->password === null && $user->identities()->count() === 1) {
            throw ValidationException::withMessages(['provider' => ['Set a password before removing your only sign-in method.']]);
        }

        $identity->delete();
    }

    /** @param array<string, mixed> $claims */
    private function createAppleIdentity(User $user, string $providerUserId, array $claims): UserIdentity
    {
        return UserIdentity::create([
            'user_id' => $user->id,
            'provider' => UserIdentity::AppleProvider,
            'provider_user_id' => $providerUserId,
            'provider_email' => is_string($claims['email'] ?? null) ? Str::lower($claims['email']) : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function claimsForAuthorizationCode(string $authorizationCode): array
    {
        $response = Http::asForm()->connectTimeout(3)->timeout(10)->post(self::TokenEndpoint, [
            'client_id' => $this->appleClientSecret->clientId(),
            'client_secret' => $this->appleClientSecret->create(),
            'code' => $authorizationCode,
            'grant_type' => 'authorization_code',
        ]);

        if (! $response->successful()) {
            throw ValidationException::withMessages(['authorization_code' => ['Apple could not validate this authorization code.']]);
        }

        $identityToken = $response->json('id_token');

        if (! is_string($identityToken)) {
            throw new RuntimeException('Apple did not return an identity token.');
        }

        return $this->appleIdentityTokenVerifier->verify($identityToken);
    }

    /** @param array<string, mixed> $claims */
    private function providerUserId(array $claims): string
    {
        $providerUserId = $claims['sub'] ?? null;

        if (! is_string($providerUserId) || $providerUserId === '') {
            throw ValidationException::withMessages(['authorization_code' => ['Apple did not return a user identifier.']]);
        }

        return $providerUserId;
    }

    /** @param array<string, mixed> $claims */
    private function verifiedEmail(array $claims): string
    {
        $email = $claims['email'] ?? null;
        $isVerified = ($claims['email_verified'] ?? null) === true || ($claims['email_verified'] ?? null) === 'true';

        if (! $isVerified || ! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['authorization_code' => ['Apple did not return a verified email address for this account.']]);
        }

        return Str::lower($email);
    }

    private function nameForNewUser(?string $fullName, string $email): string
    {
        $fullName = trim((string) preg_replace('/\\s+/', ' ', $fullName ?? ''));

        return $fullName !== '' ? $fullName : Str::before($email, '@');
    }

    /** @return Builder<UserIdentity> */
    private function appleIdentityQuery(): Builder
    {
        return UserIdentity::query()->where('provider', UserIdentity::AppleProvider);
    }
}
