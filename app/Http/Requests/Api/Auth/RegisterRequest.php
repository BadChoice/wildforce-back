<?php

namespace App\Http\Requests\Api\Auth;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    use PasswordValidationRules;

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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => $this->passwordRules(),
            'device_name' => ['required', 'string', 'max:255'],
            'training_profile' => ['required', 'array'],
            'training_profile.goal' => ['required', 'string', Rule::in(['generalFitness', 'loseWeight', 'buildMuscle', 'gainStrength', 'improveEndurance', 'improveMobility', 'bodyRecomposition'])],
            'training_profile.lifestyle' => ['required', 'string', Rule::in(['sedentary', 'lightlyActive', 'moderatelyActive', 'veryActive'])],
            'training_profile.gym_type' => ['required', 'string', Rule::in(['bigGym', 'smallGym', 'homeGym', 'someAccessories', 'bodyweightOnly'])],
            'training_profile.general_training_level' => ['required', 'string', Rule::in(['completeBeginner', 'beginner', 'novice', 'intermediate', 'advanced', 'elite'])],
            'training_profile.training_split_preference' => ['required', 'string', Rule::in(['automatic', 'fullBody', 'upperLower', 'pushPullLegs', 'bodyPartSplit', 'custom'])],
            'training_profile.preferred_workout_duration_minutes' => ['required', 'integer', 'min:10', 'max:300'],
            'training_profile.workout_days' => ['required', 'array', 'min:1', 'max:7'],
            'training_profile.workout_days.*' => ['required', 'string', 'distinct', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'training_profile.custom_workout_focuses' => ['nullable', 'array'],
            'training_profile.custom_workout_focuses.*' => ['required', 'string', Rule::in(['fullBody', 'upperBody', 'lowerBody', 'push', 'pull', 'legs', 'cardio', 'mobility', 'recovery', 'core'])],
            'training_profile.movement_restrictions' => ['nullable', 'array'],
            'training_profile.movement_restrictions.*' => ['required', 'string', Rule::in(['lowerBackPain', 'shoulderPain', 'kneePain', 'hipPain', 'anklePain', 'wristPain', 'elbowPain', 'neckPain', 'limitedShoulderMobility', 'limitedHipMobility', 'limitedAnkleMobility', 'limitedKneeFlexion', 'overheadMovementLimitation', 'impactSensitivity'])],
            'training_profile.body_composition_phase' => ['nullable', 'string', Rule::in(['automatic', 'bulk', 'cut', 'maintain'])],
            'training_profile.skips_warmups' => ['sometimes', 'boolean'],
            'training_profile.skips_cooldowns' => ['sometimes', 'boolean'],
            'training_profile.skips_rest_periods' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function trainingProfile(): array
    {
        /** @var array<string, mixed> $profile */
        $profile = $this->validated('training_profile');

        return [
            ...$profile,
            'skips_warmups' => $this->boolean('training_profile.skips_warmups'),
            'skips_cooldowns' => $this->boolean('training_profile.skips_cooldowns'),
            'skips_rest_periods' => $this->boolean('training_profile.skips_rest_periods'),
        ];
    }
}
