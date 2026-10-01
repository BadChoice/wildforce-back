<?php

namespace App\Http\Resources\Api\Sync;

use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutPlanSyncResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var WorkoutPlan $plan */
        $plan = $this->resource;

        return [
            ...$plan->syncPayload(),
            'workout_days' => $plan->workoutDays
                ->sortBy(['order_index', 'id'])
                ->map(fn (WorkoutDay $day): array => (new WorkoutDaySyncResource($day))->resolve($request))
                ->values()
                ->all(),
        ];
    }
}
