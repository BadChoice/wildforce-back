<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkoutPlan;

class WorkoutPlanPolicy
{
    public function view(User $user, WorkoutPlan $workoutPlan): bool
    {
        return $user->canViewUserData($workoutPlan->user_id);
    }
}
