<?php

namespace App\Http\Controllers\Api\Auth;

use App\Contracts\AppleAuthentication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\AppleAuthenticationRequest;
use App\Http\Requests\Api\Auth\AppleRegistrationRequest;
use Illuminate\Http\JsonResponse;

class AppleAuthenticationController extends Controller
{
    public function __construct(private AppleAuthentication $appleAuthentication) {}

    public function __invoke(AppleAuthenticationRequest $request): JsonResponse
    {
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
        ]);
    }

    public function register(AppleRegistrationRequest $request): JsonResponse
    {
        $result = $this->appleAuthentication->register(
            $request->string('authorization_code')->toString(),
            $request->string('device_name')->toString(),
            $request->string('full_name')->trim()->toString() ?: null,
            $request->initialData(),
        );

        return response()->json([
            'user' => [
                'id' => $result->user->id,
                'name' => $result->user->name,
                'email' => $result->user->email,
            ],
            'token' => $result->token,
            'token_type' => 'Bearer',
            'trial_ends_at' => $result->trialEndsAt,
        ], 201);
    }
}
