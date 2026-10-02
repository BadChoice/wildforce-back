<?php

namespace App\Http\Requests\Api\Workouts;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateSingleWorkoutRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'goal' => ['nullable', 'string', Rule::in(['generalFitness', 'loseWeight', 'buildMuscle', 'gainStrength', 'improveEndurance', 'improveMobility', 'bodyRecomposition'])],
            'focuses' => ['required', 'array', 'max:10'],
            'focuses.*' => ['string', Rule::in(['fullBody', 'upperBody', 'lowerBody', 'chest', 'back', 'shoulders', 'arms', 'core', 'cardio', 'mobility'])],
            'muscleGroups' => ['required', 'array', 'max:20'],
            'muscleGroups.*' => ['string', 'max:100'],
            'equipment' => ['required', 'array', 'min:1', 'max:50'],
            'equipment.*' => ['string', 'max:100'],
            'includeWarmup' => ['required', 'boolean'],
            'includeCooldown' => ['required', 'boolean'],
            'durationMinutes' => ['required', 'integer', 'min:10', 'max:180'],
        ];
    }

    /**
     * @return array{goal: string|null, focuses: list<string>, muscleGroups: list<string>, equipment: list<string>, includeWarmup: bool, includeCooldown: bool, durationMinutes: int}
     */
    public function singleWorkoutRequest(): array
    {
        $validated = $this->validated();

        return [
            'goal' => $validated['goal'] ?? null,
            'focuses' => $validated['focuses'],
            'muscleGroups' => $validated['muscleGroups'],
            'equipment' => $validated['equipment'],
            'includeWarmup' => $validated['includeWarmup'],
            'includeCooldown' => $validated['includeCooldown'],
            'durationMinutes' => $validated['durationMinutes'],
        ];
    }
}
