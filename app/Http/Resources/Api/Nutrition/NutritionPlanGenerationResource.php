<?php

namespace App\Http\Resources\Api\Nutrition;

use App\Models\NutritionDay;
use App\Models\NutritionMeal;
use App\Models\NutritionPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NutritionPlan */
class NutritionPlanGenerationResource extends JsonResource
{
    /**
     * Transform the generated, unpersisted nutrition plan into an API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var NutritionPlan $plan */
        $plan = $this->resource;

        return [
            'source_workout_plan_id' => $plan->source_workout_plan_id,
            'starts_on' => $plan->starts_on?->toDateString(),
            'goal' => $plan->goal,
            'body_composition_phase' => $plan->body_composition_phase,
            'daily_calorie_average' => $plan->daily_calorie_average,
            'notes' => $plan->notes,
            'days' => $plan->days
                ->sortBy('date')
                ->map(fn (NutritionDay $day): array => $this->day($day))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function day(NutritionDay $day): array
    {
        return [
            'date' => $day->date->toDateString(),
            'weekday' => $day->weekday,
            'day_type' => $day->day_type,
            'target_calories' => $day->target_calories,
            'target_protein_grams' => $day->target_protein_grams,
            'target_carbs_grams' => $day->target_carbs_grams,
            'target_fat_grams' => $day->target_fat_grams,
            'planned_workout_title' => $day->planned_workout_title,
            'planned_workout_focus' => $day->planned_workout_focus,
            'energy_demand' => $day->energy_demand,
            'notes' => $day->notes,
            'pre_workout_guidance' => $day->pre_workout_guidance,
            'post_workout_guidance' => $day->post_workout_guidance,
            'meals' => $day->meals
                ->sortBy('order_index')
                ->map(fn (NutritionMeal $meal): array => $this->meal($meal))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function meal(NutritionMeal $meal): array
    {
        return [
            'title' => $meal->title,
            'order_index' => $meal->order_index,
            'meal_type' => $meal->meal_type,
            'target_calories' => $meal->target_calories,
            'target_protein_grams' => $meal->target_protein_grams,
            'target_carbs_grams' => $meal->target_carbs_grams,
            'target_fat_grams' => $meal->target_fat_grams,
            'guidance' => $meal->guidance,
            'example_foods' => $meal->example_foods,
        ];
    }
}
