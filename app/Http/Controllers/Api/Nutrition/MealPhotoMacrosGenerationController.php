<?php

namespace App\Http\Controllers\Api\Nutrition;

use App\Ai\Agents\Nutrition\MealPhotoMacrosAgent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\Files\Base64Image;
use Laravel\Ai\Responses\StructuredAgentResponse;

class MealPhotoMacrosGenerationController extends Controller
{
    /**
     * Estimate a meal's foods and macros from a photo without persisting the photo or result.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg', 'max:5120'],
            'meal_title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var UploadedFile $image */
        $image = $validated['image'];
        $mealContext = isset($validated['meal_title']) ? " for {$validated['meal_title']}" : '';
        $notes = isset($validated['notes']) ? "\n\nUser notes:\n{$validated['notes']}" : '';

        set_time_limit(120);

        $response = (new MealPhotoMacrosAgent($request->user()->language ?? 'en'))->prompt(
            "Analyze this meal photo{$mealContext} and estimate its nutrition.{$notes}",
            [new Base64Image(base64_encode($image->get()), $image->getMimeType())],
        );

        if (! $response instanceof StructuredAgentResponse) {
            abort(502, 'The AI provider did not return structured JSON.');
        }

        return response()->json(['data' => $response->toArray()]);
    }
}
