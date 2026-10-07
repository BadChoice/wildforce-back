<?php

namespace App\Http\Resources\Api\Account;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoachResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $coach */
        $coach = $this->resource;

        return [
            'id' => $coach->id,
            'name' => $coach->name,
        ];
    }
}
