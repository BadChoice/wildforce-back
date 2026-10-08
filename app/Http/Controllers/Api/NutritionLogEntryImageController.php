<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NutritionLogEntry;
use App\Models\NutritionLogMedia;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NutritionLogEntryImageController extends Controller
{
    public function store(
        Request $request,
        NutritionLogEntry $nutritionLogEntry,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('update', $nutritionLogEntry);

        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg', 'max:5120'],
        ]);

        /** @var UploadedFile $image */
        $image = $validated['image'];
        $filename = Str::lower($nutritionLogEntry->id).'.jpg';
        $relativePath = "nutrition-log-entries/{$filename}";

        if (Storage::putFileAs('nutrition-log-entries', $image, $filename, ['visibility' => 'private']) === false) {
            throw new RuntimeException('Unable to store the nutrition log entry image.');
        }

        $media = $nutritionLogEntry->media;

        if ($media?->source !== 'localPhoto' || ! $media?->user?->is($user)) {
            $media = new NutritionLogMedia;
        }

        $media->forceFill([
            'user_id' => $user->id,
            'source' => 'localPhoto',
            'local_relative_path' => $relativePath,
            'remote_url' => null,
        ])->save();

        $nutritionLogEntry->media()->associate($media);
        $nutritionLogEntry->save();

        return response()->json([
            'data' => [
                'path' => $relativePath,
            ],
        ]);
    }

    public function show(
        Request $request,
        NutritionLogEntry $nutritionLogEntry,
    ): StreamedResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('view', $nutritionLogEntry);

        $path = $nutritionLogEntry->media?->local_relative_path;

        abort_if($path === null || Storage::missing($path), 404);

        return Storage::response($path, disposition: 'inline');
    }
}
