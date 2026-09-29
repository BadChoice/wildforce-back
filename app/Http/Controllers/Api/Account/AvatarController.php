<?php

namespace App\Http\Controllers\Api\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AvatarController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'mimes:png', 'max:2048'],
        ]);

        /** @var User $user */
        $user = $request->user();
        /** @var UploadedFile $image */
        $image = $validated['image'];
        $path = 'avatars/'.Str::lower($user->id).'.png';
        $disk = Storage::disk((string) config('filesystems.public_storage_disc'));

        if ($disk->putFileAs('avatars', $image, Str::lower($user->id).'.png', ['visibility' => 'public']) === false) {
            throw new RuntimeException('Unable to store the avatar.');
        }

        return response()->json([
            'data' => [
                'path' => $path,
                'url' => $disk->url($path),
            ],
        ]);
    }
}
