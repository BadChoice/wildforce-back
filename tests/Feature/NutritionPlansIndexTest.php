<?php

use App\Models\NutritionPlan;
use App\Models\User;

test('it lists only the authenticated user nutrition plans with their status', function () {
    $this->travelTo('2026-10-06 12:00:00');
    $user = User::factory()->create();
    $activePlan = NutritionPlan::factory()->for($user)->create(['starts_on' => '2026-10-05', 'goal' => 'buildMuscle']);
    $pastPlan = NutritionPlan::factory()->for($user)->create(['starts_on' => '2026-09-21']);
    $otherUserPlan = NutritionPlan::factory()->create(['starts_on' => '2026-10-01']);

    $this->actingAs($user)
        ->get(route('nutrition-plans.index'))
        ->assertOk()
        ->assertSeeHtml(route('nutrition-plans.index'))
        ->assertSeeInOrder(['05/10/2026 – 11/10/2026', 'Build Muscle', 'Active', '21/09/2026 – 27/09/2026', 'Past'])
        ->assertSeeHtml(route('nutrition-plans.show', $activePlan))
        ->assertSeeHtml(route('nutrition-plans.show', $pastPlan))
        ->assertDontSeeHtml(route('nutrition-plans.show', $otherUserPlan));
});

test('it redirects guests away from the nutrition plans list', function () {
    $this->get(route('nutrition-plans.index'))
        ->assertRedirect(route('login'));
});
