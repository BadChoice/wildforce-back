<?php

namespace App\Policies;

use App\Models\NutritionLogEntry;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class NutritionLogEntryPolicy
{
    public function view(User $user, NutritionLogEntry $nutritionLogEntry): Response
    {
        return $user->canViewUserData($nutritionLogEntry->user_id)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, NutritionLogEntry $nutritionLogEntry): Response
    {
        return $user->id === $nutritionLogEntry->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
