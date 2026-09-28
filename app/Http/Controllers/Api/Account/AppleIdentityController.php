<?php

namespace App\Http\Controllers\Api\Account;

use App\Contracts\AppleAuthentication;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Account\UserIdentityResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AppleIdentityController extends Controller
{
    public function __construct(private AppleAuthentication $appleAuthentication) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return UserIdentityResource::collection($this->appleAuthentication->identities($this->user($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['authorization_code' => ['required', 'string', 'max:8192']]);
        $identity = $this->appleAuthentication->link(
            $this->user($request),
            $request->string('authorization_code')->toString(),
        );

        return (new UserIdentityResource($identity))
            ->response()
            ->setStatusCode($identity->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request): Response
    {
        $this->appleAuthentication->unlink($this->user($request));

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
