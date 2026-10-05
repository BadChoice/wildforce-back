<?php

namespace App\Http\Controllers\Api\Workouts;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Sync\WorkoutPlanSyncResource;
use App\Services\Workouts\WorkoutPlanAIGenerator;
use Illuminate\Http\Request;

class WorkoutPlanGenerationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, WorkoutPlanAIGenerator $generator): WorkoutPlanSyncResource
    {
        set_time_limit(120);

        return new WorkoutPlanSyncResource($generator->generateAndPersist($request->user()));
    }
}
