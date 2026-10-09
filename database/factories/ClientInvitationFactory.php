<?php

namespace Database\Factories;

use App\Models\ClientInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ClientInvitation>
 */
class ClientInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'coach_user_id' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'message' => fake()->optional()->sentence(),
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addDays(7),
        ];
    }
}
