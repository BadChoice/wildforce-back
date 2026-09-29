<?php

namespace App\Http\Controllers\Api\Auth;

use App\Contracts\GoogleAuthentication;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoogleAuthenticationController extends Controller
{
    public function __construct(private GoogleAuthentication $googleAuthentication) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['id_token' => ['required', 'string', 'max:8192'], 'device_name' => ['required', 'string', 'max:255']]);
        $result = $this->googleAuthentication->authenticate(
            $request->string('id_token')->toString(),
            $request->string('device_name')->toString(),
        );

        return response()->json([
            'user' => [
                'id' => $result->user->id,
                'name' => $result->user->name,
                'email' => $result->user->email,
            ],
            'token' => $result->token,
            'token_type' => 'Bearer',
            ...($result->trialEndsAt === null ? [] : ['trial_ends_at' => $result->trialEndsAt]),
        ], $result->trialEndsAt === null ? 200 : 201);
    }
}
