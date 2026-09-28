<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BodyProgressPhotoSession;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class BodyProgressPhotoController extends Controller
{
    public function store(
        Request $request,
        BodyProgressPhotoSession $bodyProgressPhotoSession,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();

        Gate::authorize('update', $bodyProgressPhotoSession);

        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:jpg,jpeg', 'max:5120'],
        ]);

        /** @var UploadedFile $image */
        $image = $validated['image'];
        $angle = $request->route('angle');
        $relativePath = Str::lower($bodyProgressPhotoSession->id)."-{$angle}.jpg";

        if (Storage::putFileAs('', $image, $relativePath, ['visibility' => 'private']) === false) {
            throw new RuntimeException('Unable to store the body progress photo.');
        }

        $photoPathColumn = [
            'profile' => 'profile_photo_path',
            'front' => 'front_photo_path',
            'torso' => 'torso_photo_path',
        ][$angle];

        $bodyProgressPhotoSession->forceFill([$photoPathColumn => $relativePath])->save();

        return response()->json([
            'data' => [
                'path' => $relativePath,
            ],
        ]);
    }
}
