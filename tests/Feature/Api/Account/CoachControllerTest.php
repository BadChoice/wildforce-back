<?php

use App\Enums\CoachingEnrollmentStatus;
use App\Models\CoachingEnrollment;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('it returns only active coaches for the authenticated user', function () {
    $client = User::factory()->create();
    $activeCoach = User::factory()->create(['name' => 'Ada Coach']);
    $pausedCoach = User::factory()->create(['name' => 'Bea Coach']);
    $otherClient = User::factory()->create();
    $otherCoach = User::factory()->create(['name' => 'Cara Coach']);
    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $activeCoach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);
    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $pausedCoach->id,
        'status' => CoachingEnrollmentStatus::Paused,
        'starts_at' => now(),
    ]);
    CoachingEnrollment::create([
        'client_user_id' => $otherClient->id,
        'coach_user_id' => $otherCoach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);
    Sanctum::actingAs($client);

    $response = $this->getJson('/api/account/coaches');

    $response->assertOk()
        ->assertExactJson([
            'data' => [[
                'id' => $activeCoach->id,
                'name' => 'Ada Coach',
            ]],
        ]);
});

test('it returns 401 when no token is provided', function () {
    $response = $this->getJson('/api/account/coaches');

    $response->assertUnauthorized();
});
