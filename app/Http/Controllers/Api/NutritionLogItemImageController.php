<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NutritionLogItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class NutritionLogItemImageController extends Controller
{
    public function store(
        Request $request,
        NutritionLogItem $nutritionLogItem,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('update', $nutritionLogItem);

        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg', 'max:5120'],
        ]);

        /** @var UploadedFile $image */
        $image = $validated['image'];
        $filename = Str::lower($nutritionLogItem->id).'.jpg';
        $relativePath = "nutrition-log-items/{$filename}";

        if (Storage::putFileAs('nutrition-log-items', $image, $filename, ['visibility' => 'private']) === false) {
            throw new RuntimeException('Unable to store the nutrition log item image.');
        }

        return response()->json([
            'data' => [
                'path' => $relativePath,
            ],
        ]);
    }
}
