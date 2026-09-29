<?php

namespace App\Contracts;

use App\Models\User;
use App\Models\UserIdentity;
use App\Services\Google\Auth\GoogleAuthenticationResult;

interface GoogleAuthentication
{
    public function authenticate(string $identityToken, string $deviceName): GoogleAuthenticationResult;

    public function link(User $user, string $identityToken): UserIdentity;

    public function unlink(User $user): void;
}
