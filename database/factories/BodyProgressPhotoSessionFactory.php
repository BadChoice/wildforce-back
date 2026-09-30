<?php

namespace Database\Factories;

use App\Models\BodyProgressPhotoSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BodyProgressPhotoSession>
 */
class BodyProgressPhotoSessionFactory extends Factory
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
            'profile_photo_path' => null,
            'front_photo_path' => null,
            'torso_photo_path' => null,
        ];
    }
}
