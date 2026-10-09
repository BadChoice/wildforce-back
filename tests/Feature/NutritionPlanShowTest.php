<?php

use App\Enums\CoachingEnrollmentStatus;
use App\Models\CoachingEnrollment;
use App\Models\NutritionDay;
use App\Models\NutritionLogEntry;
use App\Models\NutritionLogItem;
use App\Models\NutritionMeal;
use App\Models\NutritionPlan;
use App\Models\User;
use Livewire\Livewire;

test('it shows a client nutrition plan with logged totals and the day detail to their coach', function () {
    $this->travelTo('2026-10-06 12:00:00');
    $client = User::factory()->create(['name' => 'Alex Client', 'timezone' => 'Europe/Madrid']);
    $coach = User::factory()->create();
    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => now(),
    ]);
    $plan = NutritionPlan::factory()->for($client)->create(['starts_on' => '2026-10-05']);
    $planDay = NutritionDay::factory()->for($plan, 'plan')->create([
        'date' => '2026-10-05',
        'target_calories' => 2000,
        'target_protein_grams' => 150,
        'planned_workout_title' => 'Lower body strength',
    ]);
    NutritionMeal::factory()->for($planDay, 'day')->create([
        'title' => 'Protein oats',
        'example_foods' => [
            ['name' => 'Oats', 'amountGrams' => 60],
            ['name' => 'Vedella magra', 'amount_grams' => 180],
            'greekYogurt',
        ],
    ]);
    $entry = NutritionLogEntry::factory()->for($client)->create(['title' => 'Chicken bowl', 'logged_at' => '2026-10-05 13:00:00']);
    NutritionLogItem::factory()->for($entry, 'entry')->create(['name' => 'Grilled chicken', 'calories' => 1850, 'protein_grams' => 140]);
    $this->actingAs($coach);

    $this->get(route('nutrition-plans.show', $plan))->assertOk();

    Livewire::test('nutrition-plans.show', ['nutritionPlan' => $plan])
        ->assertSee('Alex Client')
        ->assertSee('Lower body strength')
        ->assertSee('2,000')
        ->assertSee('1,850')
        ->assertSee('On target')
        ->assertSee('Logged food')
        ->assertSee('Chicken bowl')
        ->assertSee('15:00')
        ->call('selectDay', '2026-10-05')
        ->assertSet('showDayDetail', true)
        ->assertSee('Protein oats')
        ->assertSee('Oats')
        ->assertSee('60 g')
        ->assertSee('Vedella Magra')
        ->assertSee('180 g')
        ->assertSee('Greek Yogurt')
        ->assertSee('Chicken bowl')
        ->assertSee('Grilled chicken');
});

test('it hides a nutrition plan from users who cannot view its owner data', function () {
    $plan = NutritionPlan::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('nutrition-plans.show', $plan))
        ->assertForbidden();
});

test('it redirects guests away from a nutrition plan', function () {
    $plan = NutritionPlan::factory()->create();

    $this->get(route('nutrition-plans.show', $plan))
        ->assertRedirect(route('login'));
});
