<?php

namespace App\Http\Resources\Api\Sync;

use App\Models\NutritionDay;
use App\Models\NutritionMeal;
use App\Models\NutritionPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NutritionPlanSyncResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var NutritionPlan $plan */
        $plan = $this->resource;

        return [
            ...$plan->syncPayload(),
            'days' => $plan->days->sortBy(['date', 'id'])->map(fn (NutritionDay $day) => [
                ...$day->syncPayload(),
                'meals' => $day->meals->sortBy(['order_index', 'id'])->map(
                    fn (NutritionMeal $meal) => $meal->syncPayload(),
                )->values()->all(),
            ])->values()->all(),
        ];
    }
}
