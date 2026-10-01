<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserAppSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAppSettings>
 */
class UserAppSettingsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'is_health_kit_enabled' => fake()->boolean(),
            'is_watch_auto_tracking_enabled' => fake()->boolean(),
            'is_screen_on_during_workout_enabled' => true,
            'is_notifications_enabled' => true,
            'is_full_focus_mode_enabled' => false,
            'full_focus_selection' => null,
            'has_seen_notification_request' => true,
            'has_seen_body_progress_tutorial' => true,
            'has_rated_app' => false,
        ];
    }
}
