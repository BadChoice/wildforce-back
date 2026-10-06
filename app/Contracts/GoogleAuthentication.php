<?php

namespace App\Contracts;

use App\Models\User;
use App\Models\UserIdentity;
use App\Services\Google\Auth\GoogleAuthenticationResult;
use App\Services\Google\Auth\GoogleUserAuthenticationResult;

interface GoogleAuthentication
{
    public function authenticate(string $identityToken, string $deviceName): GoogleAuthenticationResult;

    /** @param array<string, array<string, mixed>> $initialData */
    public function register(string $identityToken, string $deviceName, array $initialData): GoogleAuthenticationResult;

    public function resolve(string $identityToken): GoogleUserAuthenticationResult;

    public function link(User $user, string $identityToken): UserIdentity;

    public function unlink(User $user): void;
}
