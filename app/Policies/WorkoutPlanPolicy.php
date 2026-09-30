<?php

namespace App\Policies;

use App\Enums\CoachingEnrollmentStatus;
use App\Models\User;
use App\Models\WorkoutPlan;

class WorkoutPlanPolicy
{
    public function view(User $user, WorkoutPlan $workoutPlan): bool
    {
        return $this->canViewWorkoutPlan($user, $workoutPlan);
    }

    public function update(User $user, WorkoutPlan $workoutPlan): bool
    {
        return $this->canViewWorkoutPlan($user, $workoutPlan);
    }

    private function canViewWorkoutPlan(User $user, WorkoutPlan $workoutPlan): bool
    {
        if ($user->isAdmin() || $user->getKey() === $workoutPlan->user_id) {
            return true;
        }

        return $user->clients(CoachingEnrollmentStatus::Active)
            ->whereKey($workoutPlan->user_id)
            ->exists();
    }
}
