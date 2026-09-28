<?php

namespace App\Services\Apple\Auth;

use App\Models\User;
use Carbon\CarbonInterface;

class AppleAuthenticationResult
{
    public function __construct(
        public User $user,
        public string $token,
        public ?CarbonInterface $trialEndsAt = null,
    ) {}
}
