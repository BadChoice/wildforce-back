<?php

namespace App\Http\Resources\Api\Sync;

use App\Models\CoachExerciseMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CoachExerciseMedia
 */
class CoachExerciseMediaSyncResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'coach_user_id' => $this->coach_user_id,
            'exercise' => $this->exercise,
            'image_url' => $this->imageUrl(),
            'youtube_video_id' => $this->youtube_video_id,
            'youtube_url' => $this->youtubeUrl(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
