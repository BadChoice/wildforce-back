<?php

use App\Ai\Agents\Nutrition\NutritionLabelMacrosAgent;
use App\Models\User;

test('it returns the per 100 g macros extracted from the label text', function () {
    $user = User::factory()->create();
    NutritionLabelMacrosAgent::fake([[
        'servingSize' => '30 g',
        'macros' => ['calories' => 389, 'protein' => 13.5, 'carbs' => 58.7, 'fat' => 7],
    ]])->preventStrayPrompts();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/nutrition-macros/analyze-label', [
        'text' => "Energy | 1628 kJ / 389 kcal\nProtein | 13,5 g",
    ], [
        'Idempotency-Key' => 'nutrition-label-macros-1',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.servingSize', '30 g')
        ->assertJsonPath('data.macros.calories', 389)
        ->assertJsonPath('data.macros.protein', 13.5);

    NutritionLabelMacrosAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Energy | 1628 kJ / 389 kcal'));
});

test('it returns 422 when the label text is missing', function () {
    $user = User::factory()->create();
    NutritionLabelMacrosAgent::fake()->preventStrayPrompts();

    $this->actingAs($user, 'sanctum')->postJson('/api/nutrition-macros/analyze-label', [], [
        'Idempotency-Key' => 'nutrition-label-macros-invalid',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['text']);
});
