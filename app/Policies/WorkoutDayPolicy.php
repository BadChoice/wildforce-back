<?php

namespace App\Policies;

use App\Enums\CoachingEnrollmentStatus;
use App\Models\User;
use App\Models\WorkoutDay;

class WorkoutDayPolicy
{
    public function update(User $user, WorkoutDay $workoutDay): bool
    {
        if ($user->isAdmin() || $user->getKey() === $workoutDay->user_id) {
            return true;
        }

        return $user->clients(CoachingEnrollmentStatus::Active)
            ->whereKey($workoutDay->user_id)
            ->exists();
    }
}
