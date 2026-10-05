<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Models\Subscription;
use App\Models\TrainingPreference;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        [$user, $trial] = DB::transaction(function () use ($request): array {
            $user = new User([
                'name' => $request->string('name')->toString(),
                'email' => $request->string('email')->lower()->toString(),
                'password' => Hash::make($request->string('password')->toString()),
            ]);

            // Keep the client's local id so records it already created stay linked to this user.
            if ($request->filled('id')) {
                $user->id = $request->string('id')->lower()->toString();
            }

            $user->save();

            $user->trainingPreferences()->save((new TrainingPreference)->forceFill($request->trainingProfile()));
            $trial = $user->attachSubscription(Subscription::createTrial());

            return [$user, $trial];
        });

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
            'trial_ends_at' => $trial->renews_at,
        ], 201);
    }
}
