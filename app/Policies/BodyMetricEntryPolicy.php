<?php

namespace App\Policies;

use App\Models\BodyMetricEntry;
use App\Models\User;

class BodyMetricEntryPolicy
{
    public function view(User $user, BodyMetricEntry $bodyMetricEntry): bool
    {
        return $user->canViewUserData($bodyMetricEntry->user_id);
    }
}
