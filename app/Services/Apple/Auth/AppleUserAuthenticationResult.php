<?php

namespace App\Services\Apple\Auth;

use App\Models\User;
use Carbon\CarbonInterface;

class AppleUserAuthenticationResult
{
    public function __construct(
        public User $user,
        public ?CarbonInterface $trialEndsAt = null,
    ) {}
}
