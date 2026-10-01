<?php

namespace App\Http\Controllers\Api\Workouts;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Workouts\WorkoutPlanGenerationResource;
use App\Services\Workouts\WorkoutPlanAIGenerator;
use Illuminate\Http\Request;

class WorkoutPlanGenerationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, WorkoutPlanAIGenerator $generator): WorkoutPlanGenerationResource
    {
        return new WorkoutPlanGenerationResource($generator->generate($request->user()));
    }
}
