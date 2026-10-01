<?php

namespace App\Services\Google\Auth;

use App\Models\User;
use Carbon\CarbonInterface;

class GoogleUserAuthenticationResult
{
    public function __construct(
        public User $user,
        public ?CarbonInterface $trialEndsAt = null,
    ) {}
}
