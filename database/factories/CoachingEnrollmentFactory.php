<?php

namespace Database\Factories;

use App\Enums\CoachingEnrollmentStatus;
use App\Models\CoachingEnrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CoachingEnrollment>
 */
class CoachingEnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_user_id' => User::factory(),
            'coach_user_id' => User::factory(),
            'status' => CoachingEnrollmentStatus::Active,
            'starts_at' => now()->subMonths(3),
            'ends_at' => null,
        ];
    }
}
