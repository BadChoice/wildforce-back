<?php

use App\Enums\CoachingEnrollmentStatus;
use App\Models\CoachingEnrollment;
use App\Models\User;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use Livewire\Livewire;

test('it displays the clients navigation item only when the authenticated user has clients', function () {
    $coach = User::factory()->create();
    $this->actingAs($coach);

    $this->get(route('clients.index'))
        ->assertDontSee(route('clients.index'));

    $client = User::factory()->create(['name' => 'Alex Client']);
    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);

    $this->get(route('clients.index'))
        ->assertSee('Clients')
        ->assertSee('Alex Client');
});

test('it displays a selected client details and workout plans', function () {
    $coach = User::factory()->create();
    $client = User::factory()->create([
        'name' => 'Alex Client',
        'height_cm' => 180,
        'weight_kg' => 80,
    ]);
    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);
    $workoutPlan = WorkoutPlan::factory()->for($client)->create(['name' => 'Strength foundation']);
    WorkoutDay::factory()->for($client)->for($workoutPlan, 'plan')->create(['title' => 'Lower body']);
    $this->actingAs($coach);

    Livewire::test('dashboard.clients')
        ->call('selectClient', $client->id)
        ->assertSet('showClientDetail', true)
        ->assertSee('Alex Client')
        ->assertSee('180 cm')
        ->assertSee('80.00 kg')
        ->call('selectTab', 'training')
        ->assertSee('Strength foundation')
        ->assertSee('Lower body')
        ->call('selectTab', 'nutrition')
        ->assertSee('Nutrition details will be available here soon.')
        ->call('selectTab', 'body-metrics')
        ->assertSee('Body metrics will be available here soon.');
});

test('it does not allow a coach to inspect a user who is not their client', function () {
    $coach = User::factory()->create();
    $otherUser = User::factory()->create();
    $this->actingAs($coach);

    Livewire::test('dashboard.clients')
        ->call('selectClient', $otherUser->id)
        ->assertSet('selectedClientId', null)
        ->assertSet('showClientDetail', false);
});
