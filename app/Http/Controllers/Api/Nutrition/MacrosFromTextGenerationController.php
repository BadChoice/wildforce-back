<?php

namespace App\Http\Controllers\Api\Nutrition;

use App\Ai\Agents\Nutrition\MacrosFromTextAgent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Ai\Responses\StructuredAgentResponse;

class MacrosFromTextGenerationController extends Controller
{
    /**
     * Estimate a meal's foods and macros from a free-text description without persisting anything.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
            'meal_title' => ['nullable', 'string', 'max:255'],
        ]);

        $mealContext = isset($validated['meal_title']) ? " for {$validated['meal_title']}" : '';
        $response = (new MacrosFromTextAgent($request->user()->language ?? 'en'))
            ->prompt("Analyze this meal description: \"{$validated['text']}\"{$mealContext} and estimate its nutrition.");

        if (! $response instanceof StructuredAgentResponse) {
            abort(502, 'The AI provider did not return structured JSON.');
        }

        return response()->json(['data' => $response->toArray()]);
    }
}
