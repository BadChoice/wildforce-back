<?php

namespace App\Http\Controllers\Api\Nutrition;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Nutrition\NutritionPlanGenerationResource;
use App\Services\Nutrition\NutritionPlanAIGenerator;
use Illuminate\Http\Request;

class NutritionPlanGenerationController extends Controller
{
    /**
     * Generate a nutrition-plan draft for the authenticated user.
     */
    public function __invoke(Request $request, NutritionPlanAIGenerator $generator): NutritionPlanGenerationResource
    {
        return new NutritionPlanGenerationResource($generator->generate($request->user()));
    }
}
