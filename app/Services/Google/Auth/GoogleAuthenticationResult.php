<?php

namespace App\Services\Google\Auth;

use App\Models\User;
use Carbon\CarbonInterface;

class GoogleAuthenticationResult
{
    public function __construct(
        public User $user,
        public string $token,
        public ?CarbonInterface $trialEndsAt = null,
    ) {}
}
