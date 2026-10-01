<?php

use App\Enums\SubscriptionPlan;
use App\Models\User;

test('it registers a user and returns a bearer token for the device', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_name' => 'Jane’s iPhone',
        'training_profile' => trainingProfilePayload(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.name', 'Jane Doe')
        ->assertJsonPath('user.email', 'jane@example.com')
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonStructure(['user' => ['id', 'name', 'email'], 'token', 'token_type', 'trial_ends_at']);

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();
    $authenticatedResponse = $this->getJson('/api/user', [
        'Authorization' => 'Bearer '.$response->json('token'),
    ]);

    expect($user->id)->toBeUuid()
        ->and($response->json('token'))->toBeString()->not->toBeEmpty()
        ->and($user->subscription->plan)->toBe(SubscriptionPlan::Trial)
        ->and($user->subscription->renews_at)->toEqual($user->subscription->starts_at->copy()->addDays(15))
        ->and($user->tokens)->toHaveCount(1)
        ->and($user->tokens->sole()->name)->toBe('Jane’s iPhone')
        ->and($user->trainingPreferences->only(['goal', 'gym_type', 'workout_days']))->toBe([
            'goal' => 'buildMuscle',
            'gym_type' => 'bigGym',
            'workout_days' => ['monday', 'wednesday', 'friday'],
        ]);

    $authenticatedResponse->assertOk()
        ->assertJsonPath('id', $user->id);
});

test('it validates the registration payload', function () {
    $response = $this->postJson('/api/auth/register', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password', 'device_name', 'training_profile']);

    $this->assertDatabaseCount('users', 0);
});

/** @return array<string, mixed> */
function trainingProfilePayload(): array
{
    return [
        'goal' => 'buildMuscle',
        'lifestyle' => 'moderatelyActive',
        'gym_type' => 'bigGym',
        'general_training_level' => 'intermediate',
        'training_split_preference' => 'upperLower',
        'preferred_workout_duration_minutes' => 60,
        'workout_days' => ['monday', 'wednesday', 'friday'],
        'custom_workout_focuses' => null,
        'movement_restrictions' => ['shoulderPain'],
        'body_composition_phase' => 'bulk',
        'skips_warmups' => false,
        'skips_cooldowns' => true,
        'skips_rest_periods' => false,
    ];
}
