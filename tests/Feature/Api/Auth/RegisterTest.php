<?php

use App\Enums\SubscriptionPlan;
use App\Models\User;

test('it registers a user and returns a bearer token for the device', function () {
    $response = $this->postJson('/api/auth/register', [
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_name' => 'Jane’s iPhone',
        'initial_data' => initialDataPayload(),
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
        ->and($user->language)->toBe('ca')
        ->and($user->timezone)->toBe('Europe/Madrid')
        ->and($user->id)->toBe('7c9e6679-7425-40de-944b-e07fc1f90ae7')
        ->and($user->trainingPreferences->only(['goal', 'gym_type', 'workout_days']))->toBe([
            'goal' => 'buildMuscle',
            'gym_type' => 'bigGym',
            'workout_days' => ['monday', 'wednesday', 'friday'],
        ])
        ->and($user->trainingLocations)->toHaveCount(1)
        ->and($user->trainingLocations->sole()->only(['id', 'is_default']))->toBe([
            'id' => 'c1d2e3f4-a5b6-4789-8abc-def012345678',
            'is_default' => true,
        ])
        ->and($user->trainingLocations->sole()->equipment->map->value->all())->toBe(['bodyweight', 'dumbbells'])
        ->and($user->appSettings->id)->toBe('d1d2e3f4-a5b6-4789-8abc-def012345678');

    $authenticatedResponse->assertOk()
        ->assertJsonPath('id', $user->id);
});

test('it keeps the client generated training preferences id', function () {
    $preferencesId = '0b6f5c3e-2a8d-4f1b-9c7e-5d4a3b2c1e0f';

    $this->postJson('/api/auth/register', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'language' => 'ca',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_name' => 'Jane’s iPhone',
        'initial_data' => initialDataPayload(['training_preferences' => ['id' => $preferencesId]]),
    ])->assertCreated();

    $user = User::query()->where('email', 'jane@example.com')->firstOrFail();

    expect($user->trainingPreferences->id)->toBe($preferencesId);
});

test('it keeps the client generated user id', function () {
    $userId = '7C9E6679-7425-40DE-944B-E07FC1F90AE7';

    $response = $this->postJson('/api/auth/register', [
        'email' => 'jane@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_name' => 'Jane’s iPhone',
        'initial_data' => initialDataPayload(['user' => ['id' => $userId]]),
    ]);

    $response->assertCreated()->assertJsonPath('user.id', strtolower($userId));
    expect(User::query()->whereKey(strtolower($userId))->exists())->toBeTrue();
});

test('it validates the registration payload', function () {
    $response = $this->postJson('/api/auth/register', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password', 'device_name', 'initial_data']);

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

/**
 * @param  array<string, array<string, mixed>>  $overrides
 * @return array<string, array<string, mixed>>
 */
function initialDataPayload(array $overrides = []): array
{
    $data = [
        'user' => [
            'id' => '7c9e6679-7425-40de-944b-e07fc1f90ae7',
            'name' => 'Jane Doe',
            'height' => 168,
            'weight' => 61.5,
            'birth_date' => '1995-01-01T00:00:00Z',
            'gender' => 'female',
            'language' => 'ca',
            'timezone' => 'Europe/Madrid',
            'metric_system' => 'metric',
        ],
        'training_preferences' => [...trainingProfilePayload(), 'id' => '0b6f5c3e-2a8d-4f1b-9c7e-5d4a3b2c1e0f'],
        'training_location' => [
            'id' => 'c1d2e3f4-a5b6-4789-8abc-def012345678',
            'name' => 'Default',
            'is_default' => true,
            'sort_order' => 0,
            'equipment' => ['bodyweight', 'dumbbells'],
        ],
        'app_settings' => [
            'id' => 'd1d2e3f4-a5b6-4789-8abc-def012345678',
            'is_health_kit_enabled' => true,
            'is_watch_auto_tracking_enabled' => false,
            'is_screen_on_during_workout_enabled' => false,
            'is_notifications_enabled' => false,
            'is_full_focus_mode_enabled' => false,
            'full_focus_selection' => null,
            'has_seen_notification_request' => false,
            'has_seen_body_progress_tutorial' => false,
            'has_rated_app' => false,
        ],
    ];

    foreach ($overrides as $resource => $values) {
        $data[$resource] = [...$data[$resource], ...$values];
    }

    return $data;
}
