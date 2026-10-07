<?php

namespace App\Http\Requests\Api\Nutrition;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GenerateNutritionPlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The date window allows one day of slack on each side because the
     * client reports Apple Health days in its own time zone.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'daily_energy' => ['sometimes', 'array', 'max:31'],
            'daily_energy.*' => ['array:date,active_calories,basal_calories'],
            'daily_energy.*.date' => [
                'required',
                'date_format:Y-m-d',
                'distinct',
                'after_or_equal:'.now()->subDays(22)->toDateString(),
                'before_or_equal:'.now()->addDay()->toDateString(),
            ],
            'daily_energy.*.active_calories' => ['required', 'numeric', 'min:0', 'max:10000'],
            'daily_energy.*.basal_calories' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ];
    }

    /**
     * @return list<array{date: string, active_calories: float, basal_calories: float|null}>
     */
    public function dailyEnergy(): array
    {
        $dailyEnergy = [];

        foreach ($this->array('daily_energy') as $day) {
            $dailyEnergy[] = [
                'date' => (string) $day['date'],
                'active_calories' => (float) $day['active_calories'],
                'basal_calories' => isset($day['basal_calories']) ? (float) $day['basal_calories'] : null,
            ];
        }

        return $dailyEnergy;
    }
}
