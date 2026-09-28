<?php

namespace App\Policies;

use App\Models\NutritionPlan;
use App\Models\User;

class NutritionPlanPolicy
{
    public function view(User $user, NutritionPlan $nutritionPlan): bool
    {
        return $user->canViewUserData($nutritionPlan->user_id);
    }
}
