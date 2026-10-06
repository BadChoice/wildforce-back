<?php

namespace App\Http\Requests\Api\Auth;

use App\Enums\Equipment;
use App\Enums\Goal;
use App\Enums\WorkoutFocus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class InitialRegistrationDataRequest extends FormRequest
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
    protected function initialDataRules(bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';
        $nested = $required ? 'required' : 'required_with:initial_data';

        return [
            'initial_data' => [$presence, 'array'],
            'initial_data.user' => [$nested, 'array'],
            'initial_data.user.id' => [$nested, 'uuid', 'unique:users,id'],
            'initial_data.user.name' => [$nested, 'string', 'max:255'],
            'initial_data.user.height' => [$nested, 'integer', 'min:50', 'max:300'],
            'initial_data.user.weight' => [$nested, 'numeric', 'min:10', 'max:1000'],
            'initial_data.user.birth_date' => [$nested, 'date'],
            'initial_data.user.gender' => [$nested, 'string', Rule::in(['female', 'male'])],
            'initial_data.user.language' => [$nested, 'string', 'max:255'],
            'initial_data.user.metric_system' => [$nested, 'string', Rule::in(['metric', 'imperial'])],
            'initial_data.training_preferences' => [$nested, 'array'],
            'initial_data.training_preferences.id' => [$nested, 'uuid', 'unique:training_preferences,id'],
            'initial_data.training_preferences.goal' => [$nested, 'string', Rule::in(Goal::allCasesArray())],
            'initial_data.training_preferences.lifestyle' => [$nested, 'string', Rule::in(['sedentary', 'lightlyActive', 'moderatelyActive', 'veryActive'])],
            'initial_data.training_preferences.gym_type' => [$nested, 'string', Rule::in(['bigGym', 'smallGym', 'homeGym', 'someAccessories', 'bodyweightOnly'])],
            'initial_data.training_preferences.general_training_level' => [$nested, 'string', Rule::in(['completeBeginner', 'beginner', 'novice', 'intermediate', 'advanced', 'elite'])],
            'initial_data.training_preferences.training_split_preference' => [$nested, 'string', Rule::in(['automatic', 'fullBody', 'upperLower', 'pushPullLegs', 'bodyPartSplit', 'custom'])],
            'initial_data.training_preferences.preferred_workout_duration_minutes' => [$nested, 'integer', 'min:10', 'max:300'],
            'initial_data.training_preferences.workout_days' => [$nested, 'array', 'min:1', 'max:7'],
            'initial_data.training_preferences.workout_days.*' => ['required', 'string', 'distinct', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'initial_data.training_preferences.custom_workout_focuses' => ['nullable', 'array'],
            'initial_data.training_preferences.custom_workout_focuses.*' => ['required', 'string', Rule::in(WorkoutFocus::allCasesArray())],
            'initial_data.training_preferences.movement_restrictions' => ['nullable', 'array'],
            'initial_data.training_preferences.movement_restrictions.*' => ['required', 'string'],
            'initial_data.training_preferences.body_composition_phase' => [$nested, 'string', Rule::in(['automatic', 'bulk', 'cut', 'maintain'])],
            'initial_data.training_preferences.skips_warmups' => [$nested, 'boolean'],
            'initial_data.training_preferences.skips_cooldowns' => [$nested, 'boolean'],
            'initial_data.training_preferences.skips_rest_periods' => [$nested, 'boolean'],
            'initial_data.training_location' => [$nested, 'array'],
            'initial_data.training_location.id' => [$nested, 'uuid', 'unique:training_locations,id'],
            'initial_data.training_location.name' => [$nested, 'string', 'max:255'],
            'initial_data.training_location.is_default' => [$nested, 'boolean'],
            'initial_data.training_location.sort_order' => [$nested, 'integer', 'min:0'],
            'initial_data.training_location.equipment' => [$nested, 'array', 'min:1'],
            'initial_data.training_location.equipment.*' => ['required', 'string', 'distinct', Rule::in(Equipment::allCasesArray())],
            'initial_data.app_settings' => [$nested, 'array'],
            'initial_data.app_settings.id' => [$nested, 'uuid', 'unique:user_app_settings,id'],
            'initial_data.app_settings.is_health_kit_enabled' => [$nested, 'boolean'],
            'initial_data.app_settings.is_watch_auto_tracking_enabled' => [$nested, 'boolean'],
            'initial_data.app_settings.is_screen_on_during_workout_enabled' => [$nested, 'boolean'],
            'initial_data.app_settings.is_notifications_enabled' => [$nested, 'boolean'],
            'initial_data.app_settings.is_full_focus_mode_enabled' => [$nested, 'boolean'],
            'initial_data.app_settings.full_focus_selection' => ['nullable', 'array'],
            'initial_data.app_settings.has_seen_notification_request' => [$nested, 'boolean'],
            'initial_data.app_settings.has_seen_body_progress_tutorial' => [$nested, 'boolean'],
            'initial_data.app_settings.has_rated_app' => [$nested, 'boolean'],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public function initialData(): array
    {
        /** @var array<string, array<string, mixed>> $data */
        $data = $this->validated('initial_data');

        foreach (['user', 'training_preferences', 'training_location', 'app_settings'] as $resource) {
            $data[$resource]['id'] = strtolower((string) $data[$resource]['id']);
        }

        return $data;
    }
}
