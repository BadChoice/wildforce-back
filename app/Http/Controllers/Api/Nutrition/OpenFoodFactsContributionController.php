<?php

namespace App\Http\Controllers\Api\Nutrition;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Nutrition\StoreOpenFoodFactsContributionRequest;
use App\Services\Nutrition\OpenFoodFactsService;
use Illuminate\Http\JsonResponse;

class OpenFoodFactsContributionController extends Controller
{
    /**
     * Contribute a nutrition product and its source images to Open Food Facts.
     */
    public function __invoke(StoreOpenFoodFactsContributionRequest $request, OpenFoodFactsService $openFoodFacts): JsonResponse
    {
        return response()->json(['data' => $openFoodFacts->contribute($request->contribution())]);
    }
}
