<?php

namespace App\Http\Resources\Api\Sync;

use App\Contracts\Syncable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SyncModelResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Syncable $model */
        $model = $this->resource;

        return $model->syncPayload();
    }
}
