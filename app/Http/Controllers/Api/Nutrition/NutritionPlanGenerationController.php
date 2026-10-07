<?php

namespace App\Http\Controllers\Api\Nutrition;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Nutrition\GenerateNutritionPlanRequest;
use App\Http\Resources\Api\Sync\NutritionPlanSyncResource;
use App\Services\Nutrition\NutritionPlanAIGenerator;

class NutritionPlanGenerationController extends Controller
{
    /**
     * Generate and persist a nutrition plan for the authenticated user.
     */
    public function __invoke(GenerateNutritionPlanRequest $request, NutritionPlanAIGenerator $generator): NutritionPlanSyncResource
    {
        set_time_limit(120);

        return new NutritionPlanSyncResource($generator->generateAndPersist($request->user(), $request->dailyEnergy()));
    }
}
