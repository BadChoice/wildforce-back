<?php

namespace App\Http\Controllers\Api\Auth;

use App\Contracts\GoogleAuthentication;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\GoogleRegistrationRequest;
use Illuminate\Http\JsonResponse;

class GoogleRegistrationController extends Controller
{
    public function __construct(private GoogleAuthentication $googleAuthentication) {}

    public function __invoke(GoogleRegistrationRequest $request): JsonResponse
    {
        $result = $this->googleAuthentication->register(
            $request->string('id_token')->toString(),
            $request->string('device_name')->toString(),
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
