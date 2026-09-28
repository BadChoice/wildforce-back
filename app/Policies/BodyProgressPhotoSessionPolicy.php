<?php

namespace App\Policies;

use App\Models\BodyProgressPhotoSession;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BodyProgressPhotoSessionPolicy
{
    public function update(User $user, BodyProgressPhotoSession $bodyProgressPhotoSession): Response
    {
        return $user->id === $bodyProgressPhotoSession->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
