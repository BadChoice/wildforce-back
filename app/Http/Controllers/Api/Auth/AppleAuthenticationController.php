<?php

namespace App\Http\Controllers\Api\Auth;

use App\Contracts\AppleAuthentication;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppleAuthenticationController extends Controller
{
    public function __construct(private AppleAuthentication $appleAuthentication) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate(['authorization_code' => ['required', 'string', 'max:8192'], 'device_name' => ['required', 'string', 'max:255'], 'full_name' => ['nullable', 'string', 'max:255']]);
        $result = $this->appleAuthentication->authenticate(
            $request->string('authorization_code')->toString(),
            $request->string('device_name')->toString(),
            $request->string('full_name')->trim()->toString() ?: null,
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
