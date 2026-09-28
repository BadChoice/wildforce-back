<?php

namespace App\Http\Resources\Api\Account;

use App\Models\UserIdentity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin UserIdentity */
class UserIdentityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'provider' => $this->provider,
            'email' => $this->provider_email,
            'linked_at' => $this->created_at,
        ];
    }
}
