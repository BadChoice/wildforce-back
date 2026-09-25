<?php

namespace App\Http\Requests\Api\Sync;

use App\Models\NutritionDay;
use App\Models\NutritionMeal;
use App\Models\NutritionPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class NutritionPlanSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'records' => ['required', 'array', 'max:100'],
            'records.*' => ['array'],
            'records.*.id' => ['required', 'uuid'],
            'records.*.created_at' => ['required', 'date'],
            'records.*.updated_at' => ['required', 'date'],
            'records.*.deleted_at' => ['nullable', 'date'],
            'records.*.days' => ['present', 'array'],
            'records.*.days.*' => ['array'],
            'records.*.days.*.id' => ['required', 'uuid'],
            'records.*.days.*.created_at' => ['required', 'date'],
            'records.*.days.*.updated_at' => ['required', 'date'],
            'records.*.days.*.deleted_at' => ['nullable', 'date'],
            'records.*.days.*.meals' => ['present', 'array'],
            'records.*.days.*.meals.*' => ['array'],
            'records.*.days.*.meals.*.id' => ['required', 'uuid'],
            'records.*.days.*.meals.*.created_at' => ['required', 'date'],
            'records.*.days.*.meals.*.updated_at' => ['required', 'date'],
            'records.*.days.*.meals.*.deleted_at' => ['nullable', 'date'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ($this->input('records', []) as $planIndex => $plan) {
                if (! is_array($plan)) {
                    continue;
                }

                $this->validateRecord($validator, "records.{$planIndex}", $plan, NutritionPlan::class, [], ['days']);

                foreach ($plan['days'] ?? [] as $dayIndex => $day) {
                    if (! is_array($day)) {
                        continue;
                    }

                    $dayPath = "records.{$planIndex}.days.{$dayIndex}";
                    $this->validateRecord($validator, $dayPath, $day, NutritionDay::class, ['nutrition_plan_id'], ['meals']);

                    foreach ($day['meals'] ?? [] as $mealIndex => $meal) {
                        if (is_array($meal)) {
                            $this->validateRecord($validator, "{$dayPath}.meals.{$mealIndex}", $meal, NutritionMeal::class, ['nutrition_day_id']);
                        }
                    }
                }
            }
        }];
    }

    /** @return list<array<string, mixed>> */
    public function records(): array
    {
        return $this->input('records', []);
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  class-string<NutritionPlan|NutritionDay|NutritionMeal>  $modelClass
     * @param  list<string>  $excludedAttributes
     * @param  list<string>  $nestedKeys
     */
    private function validateRecord(Validator $validator, string $path, array $record, string $modelClass, array $excludedAttributes = [], array $nestedKeys = []): void
    {
        $allowedAttributes = array_diff($modelClass::syncAttributes(), $excludedAttributes);
        $allowedKeys = ['id', 'created_at', 'updated_at', 'deleted_at', ...$allowedAttributes, ...$nestedKeys];

        if (array_diff(array_keys($record), $allowedKeys) !== []) {
            $validator->errors()->add($path, 'The record contains attributes that cannot be synchronized.');
        }
    }
}
