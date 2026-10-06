<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Services\Auth\InitialRegistrationService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request, InitialRegistrationService $registrationService): JsonResponse
    {
        $user = $registrationService->register(
            $request->initialData(),
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        event(new Registered($user));

        $token = $user->createToken($request->string('device_name')->toString());

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'trial_ends_at' => $user->subscription->renews_at,
        ], 201);
    }
}
