<?php

namespace App\Http\Controllers\Api\Account;

use App\Enums\CoachingEnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Account\CoachResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CoachController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $coaches = $request->user()
            ->coaches()
            ->wherePivot('status', CoachingEnrollmentStatus::Active->value)
            ->orderBy('users.name')
            ->get(['users.id', 'users.name']);

        return CoachResource::collection($coaches);
    }
}
