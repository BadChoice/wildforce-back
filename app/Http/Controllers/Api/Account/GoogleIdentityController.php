<?php

namespace App\Http\Controllers\Api\Account;

use App\Contracts\GoogleAuthentication;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Account\UserIdentityResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GoogleIdentityController extends Controller
{
    public function __construct(private GoogleAuthentication $googleAuthentication) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate(['id_token' => ['required', 'string', 'max:8192']]);
        $identity = $this->googleAuthentication->link(
            $this->user($request),
            $request->string('id_token')->toString(),
        );

        return (new UserIdentityResource($identity))
            ->response()
            ->setStatusCode($identity->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request): Response
    {
        $this->googleAuthentication->unlink($this->user($request));

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
