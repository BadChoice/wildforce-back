<?php

namespace App\Contracts;

use App\Models\User;
use App\Models\UserIdentity;
use App\Services\Apple\Auth\AppleAuthenticationResult;
use App\Services\Apple\Auth\AppleUserAuthenticationResult;
use Illuminate\Support\Collection;

interface AppleAuthentication
{
    public function authenticate(string $authorizationCode, string $deviceName, ?string $fullName): AppleAuthenticationResult;

    public function resolveWeb(string $authorizationCode, ?string $fullName): AppleUserAuthenticationResult;

    public function link(User $user, string $authorizationCode): UserIdentity;

    /**
     * @return Collection<int, UserIdentity>
     */
    public function identities(User $user): Collection;

    public function unlink(User $user): void;
}
