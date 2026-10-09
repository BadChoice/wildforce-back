<?php

use App\Ai\Agents\Nutrition\MealPhotoMacrosAgent;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Laravel\Ai\Files\Base64Image;

test('it returns estimated macros for a meal photo without persisting it', function () {
    $user = User::factory()->create(['language' => 'es']);
    MealPhotoMacrosAgent::fake([mealPhotoMacrosResponse()])->preventStrayPrompts();

    $response = $this->actingAs($user, 'sanctum')->post('/api/nutrition-macros/analyze-photo', [
        'image' => UploadedFile::fake()->image('meal.jpg'),
        'meal_title' => 'Lunch',
        'notes' => 'The plate is large.',
    ], [
        'Idempotency-Key' => 'meal-photo-macros-1',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.title', 'Pollo con arroz')
        ->assertJsonPath('data.estimatedFoods.0.canonicalFoodName', 'chicken breast, grilled')
        ->assertJsonPath('data.estimatedFoods.0.amountGrams', 160)
        ->assertJsonPath('data.estimatedMacros.calories', 498);

    MealPhotoMacrosAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('meal photo for Lunch')
        && $prompt->contains('User notes:')
        && $prompt->attachments->count() === 1
        && $prompt->attachments->every(fn (mixed $attachment): bool => $attachment instanceof Base64Image));
});

test('it returns 422 when the meal photo is missing', function () {
    $user = User::factory()->create();
    MealPhotoMacrosAgent::fake()->preventStrayPrompts();

    $this->actingAs($user, 'sanctum')->postJson('/api/nutrition-macros/analyze-photo', [], [
        'Idempotency-Key' => 'meal-photo-macros-missing-image',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['image'])
        ->assertJsonPath('errors.image.0', 'The image field is required.');

    MealPhotoMacrosAgent::assertNeverPrompted();
});

test('it returns 401 when no token is provided', function () {
    $this->post('/api/nutrition-macros/analyze-photo', [
        'image' => UploadedFile::fake()->image('meal.jpg'),
    ])->assertUnauthorized();
});

test('it returns 403 when the user does not have app access', function () {
    $user = User::factory()->create();
    $user->subscription()->update(['status' => SubscriptionStatus::Expired]);
    MealPhotoMacrosAgent::fake()->preventStrayPrompts();

    $this->actingAs($user, 'sanctum')->post('/api/nutrition-macros/analyze-photo', [
        'image' => UploadedFile::fake()->image('meal.jpg'),
    ], [
        'Idempotency-Key' => 'meal-photo-macros-expired',
    ])->assertForbidden()
        ->assertJsonPath('code', 'subscription_required');

    MealPhotoMacrosAgent::assertNeverPrompted();
});

/** @return array<string, mixed> */
function mealPhotoMacrosResponse(): array
{
    return [
        'title' => 'Pollo con arroz',
        'estimatedFoods' => [
            [
                'name' => 'Pechuga de pollo a la plancha',
                'canonicalFoodName' => 'chicken breast, grilled',
                'amountGrams' => 160,
                'estimatedMacros' => ['calories' => 264, 'protein' => 50, 'carbs' => 0, 'fat' => 6],
            ],
            [
                'name' => 'Arroz blanco cocido',
                'canonicalFoodName' => 'white rice, cooked',
                'amountGrams' => 180,
                'estimatedMacros' => ['calories' => 234, 'protein' => 4, 'carbs' => 50, 'fat' => 1],
            ],
        ],
        'estimatedMacros' => ['calories' => 498, 'protein' => 54, 'carbs' => 50, 'fat' => 7],
        'analysisNotes' => 'Valores estimados a partir de la imagen.',
    ];
}
