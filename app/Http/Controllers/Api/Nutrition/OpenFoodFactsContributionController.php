<?php

namespace App\Http\Controllers\Api\Nutrition;

use App\Http\Controllers\Controller;
use App\Services\Nutrition\OpenFoodFactsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OpenFoodFactsContributionController extends Controller
{
    /**
     * Contribute a nutrition product and its source images to Open Food Facts.
     */
    public function __invoke(Request $request, OpenFoodFactsService $openFoodFacts): JsonResponse
    {
        $nutriscore = $request->input('nutriscore');
        if (is_string($nutriscore)) {
            $request->merge(['nutriscore' => Str::lower($nutriscore)]);
        }

        $validated = $request->validate([
            'barcode' => ['required', 'string', 'regex:/^\d{8,14}$/'],
            'name' => ['required', 'string', 'max:255'],
            'brands' => ['required', 'string', 'max:255'],
            'macros_per_100g' => ['required', 'array:calories,protein,carbs,fat'],
            'macros_per_100g.calories' => ['required', 'numeric', 'gt:0', 'max:10000'],
            'macros_per_100g.protein' => ['required', 'numeric', 'min:0', 'max:1000'],
            'macros_per_100g.carbs' => ['required', 'numeric', 'min:0', 'max:1000'],
            'macros_per_100g.fat' => ['required', 'numeric', 'min:0', 'max:1000'],
            'serving_size' => ['nullable', 'string', 'max:100'],
            'nutriscore' => ['nullable', 'string', Rule::in(['a', 'b', 'c', 'd', 'e'])],
            'front_image' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/gif,image/heic,image/heif', 'max:10240'],
            'nutrition_image' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/gif,image/heic,image/heif', 'max:10240'],
        ]);

        /** @var array{barcode: string, name: string, brands: string, macros_per_100g: array{calories: float|int, protein: float|int, carbs: float|int, fat: float|int}, serving_size: string|null, nutriscore: string|null, front_image: UploadedFile, nutrition_image: UploadedFile} $contribution */
        $contribution = [
            'barcode' => $validated['barcode'],
            'name' => $validated['name'],
            'brands' => $validated['brands'],
            'macros_per_100g' => $validated['macros_per_100g'],
            'serving_size' => $validated['serving_size'] ?? null,
            'nutriscore' => $validated['nutriscore'] ?? null,
            'front_image' => $validated['front_image'],
            'nutrition_image' => $validated['nutrition_image'],
        ];

        return response()->json(['data' => $openFoodFacts->contribute($contribution)]);
    }
}
