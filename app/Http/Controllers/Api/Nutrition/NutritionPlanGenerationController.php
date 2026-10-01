<?php

namespace App\Http\Controllers\Api\Nutrition;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Sync\NutritionPlanSyncResource;
use App\Services\Nutrition\NutritionPlanAIGenerator;
use Illuminate\Http\Request;

class NutritionPlanGenerationController extends Controller
{
    /**
     * Generate and persist a nutrition plan for the authenticated user.
     */
    public function __invoke(Request $request, NutritionPlanAIGenerator $generator): NutritionPlanSyncResource
    {
        return new NutritionPlanSyncResource($generator->generateAndPersist($request->user()));
    }
}
