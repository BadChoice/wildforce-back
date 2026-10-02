<?php

namespace App\Http\Controllers\Api\Workouts;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Workouts\GenerateSingleWorkoutRequest;
use App\Http\Resources\Api\Workouts\GeneratedWorkoutDayResource;
use App\Services\Workouts\SingleWorkoutAIGenerator;

class SingleWorkoutGenerationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(GenerateSingleWorkoutRequest $request, SingleWorkoutAIGenerator $generator): GeneratedWorkoutDayResource
    {
        return new GeneratedWorkoutDayResource($generator->generate($request->user(), $request->singleWorkoutRequest()));
    }
}
