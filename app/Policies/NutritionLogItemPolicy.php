<?php

namespace App\Policies;

use App\Models\NutritionLogItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class NutritionLogItemPolicy
{
    public function update(User $user, NutritionLogItem $nutritionLogItem): Response
    {
        return $user->id === $nutritionLogItem->entry?->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
