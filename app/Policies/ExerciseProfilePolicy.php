<?php

namespace App\Policies;

use App\Models\ExerciseProfile;
use App\Models\User;

class ExerciseProfilePolicy
{
    public function view(User $user, ExerciseProfile $exerciseProfile): bool
    {
        return $user->canViewUserData($exerciseProfile->user_id);
    }
}
