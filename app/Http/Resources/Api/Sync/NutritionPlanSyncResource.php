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
                ...$this->withoutRelationshipKeys($day->syncPayload(), ['nutrition_plan_id']),
                'meals' => $day->meals->sortBy(['order_index', 'id'])->map(
                    fn (NutritionMeal $meal) => $this->withoutRelationshipKeys($meal->syncPayload(), ['nutrition_day_id']),
                )->values()->all(),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function withoutRelationshipKeys(array $payload, array $keys): array
    {
        return array_diff_key($payload, array_flip($keys));
    }
}
