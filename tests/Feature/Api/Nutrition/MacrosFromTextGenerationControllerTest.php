<?php

use App\Ai\Agents\Nutrition\MacrosFromTextAgent;
use App\Enums\SubscriptionStatus;
use App\Models\User;

test('it returns estimated macros for a meal description without persisting it', function () {
    $user = User::factory()->create(['language' => 'es']);
    MacrosFromTextAgent::fake([macrosFromTextResponse()])->preventStrayPrompts();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/nutrition-macros/generate', [
        'text' => 'Two fried eggs with toast',
        'meal_title' => 'Breakfast',
    ], [
        'Idempotency-Key' => 'macros-from-text-1',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.title', 'Huevos con tostada')
        ->assertJsonPath('data.estimatedFoods.0.canonicalFoodName', 'egg, fried')
        ->assertJsonPath('data.estimatedFoods.0.amountGrams', 100)
        ->assertJsonPath('data.estimatedMacros.calories', 330);

    MacrosFromTextAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('"Two fried eggs with toast" for Breakfast'));
});

test('it returns 422 when the description is missing', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->postJson('/api/nutrition-macros/generate', [], [
        'Idempotency-Key' => 'macros-from-text-invalid',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['text']);
});

test('it returns 401 when no token is provided', function () {
    $this->postJson('/api/nutrition-macros/generate', ['text' => 'An apple'])
        ->assertUnauthorized();
});

test('it returns 403 when the user does not have app access', function () {
    $user = User::factory()->create();
    $user->subscription()->update(['status' => SubscriptionStatus::Expired]);

    $this->actingAs($user, 'sanctum')->postJson('/api/nutrition-macros/generate', ['text' => 'An apple'], [
        'Idempotency-Key' => 'macros-from-text-expired',
    ])->assertForbidden()
        ->assertJsonPath('code', 'subscription_required');
});

/** @return array<string, mixed> */
function macrosFromTextResponse(): array
{
    return [
        'title' => 'Huevos con tostada',
        'estimatedFoods' => [
            [
                'name' => 'Huevos fritos',
                'canonicalFoodName' => 'egg, fried',
                'amountGrams' => 100,
                'estimatedMacros' => ['calories' => 196, 'protein' => 14, 'carbs' => 1, 'fat' => 15],
            ],
            [
                'name' => 'Tostada',
                'canonicalFoodName' => 'bread, toasted',
                'amountGrams' => 50,
                'estimatedMacros' => ['calories' => 134, 'protein' => 4, 'carbs' => 24, 'fat' => 2],
            ],
        ],
        'estimatedMacros' => ['calories' => 330, 'protein' => 18, 'carbs' => 25, 'fat' => 17],
        'analysisNotes' => 'Valores estimados a partir de la descripción.',
    ];
}
