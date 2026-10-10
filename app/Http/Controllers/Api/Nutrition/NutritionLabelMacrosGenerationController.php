<?php

namespace App\Http\Controllers\Api\Nutrition;

use App\Ai\Agents\Nutrition\NutritionLabelMacrosAgent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Ai\Responses\StructuredAgentResponse;

class NutritionLabelMacrosGenerationController extends Controller
{
    /**
     * Extract the per 100 g macros from the OCR text of a nutrition label without persisting anything.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:5000'],
        ]);

        $response = (new NutritionLabelMacrosAgent)
            ->prompt("Extract the macros from this nutrition label:\n\n{$validated['text']}");

        if (! $response instanceof StructuredAgentResponse) {
            abort(502, 'The AI provider did not return structured JSON.');
        }

        return response()->json(['data' => $response->toArray()]);
    }
}
