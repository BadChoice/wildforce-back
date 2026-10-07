<?php

use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

test('it contributes a product to Open Food Facts', function () {
    config()->set('services.open_food_facts.user_id', 'wildforce');
    config()->set('services.open_food_facts.password', 'secret');
    Http::preventStrayRequests();
    Http::fake([
        'https://world.openfoodfacts.org/cgi/product_jqm2.pl' => Http::response([
            'code' => '8412345678901',
            'status' => 'status ok',
        ]),
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->post('/api/nutrition/open-food-facts/contributions', contributionPayload(), [
        'Idempotency-Key' => 'open-food-facts-contribution-1',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.code', '8412345678901')
        ->assertJsonPath('data.status', 'status ok');

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://world.openfoodfacts.org/cgi/product_jqm2.pl'
            && $request->method() === 'POST'
            && str_contains($request->body(), 'name="code"')
            && str_contains($request->body(), '8412345678901')
            && str_contains($request->body(), 'name="product_name"')
            && str_contains($request->body(), 'Protein yogurt')
            && str_contains($request->body(), 'name="brands"')
            && str_contains($request->body(), 'Wildforce')
            && str_contains($request->body(), 'name="user_id"')
            && str_contains($request->body(), 'name="password"')
            && $request->hasFile('imgupload_front', null, 'front.jpg')
            && $request->hasFile('imgupload_nutrition', null, 'nutrition.jpg');
    });
});

test('it returns 401 when no token is provided', function () {
    $this->post('/api/nutrition/open-food-facts/contributions', contributionPayload())
        ->assertUnauthorized();
});

test('it returns 403 when the user does not have app access', function () {
    $user = User::factory()->create();
    $user->subscription()->update(['status' => SubscriptionStatus::Expired]);

    $this->actingAs($user, 'sanctum')->post('/api/nutrition/open-food-facts/contributions', contributionPayload(), [
        'Idempotency-Key' => 'open-food-facts-contribution-expired',
    ])->assertForbidden()
        ->assertJsonPath('code', 'subscription_required');
});

test('it returns 422 when required contribution data is missing', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->postJson('/api/nutrition/open-food-facts/contributions', [], [
        'Idempotency-Key' => 'open-food-facts-contribution-invalid',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors([
            'barcode',
            'name',
            'brands',
            'macros_per_100g',
            'front_image',
            'nutrition_image',
        ]);
});

test('it returns 502 when Open Food Facts rejects the contribution', function () {
    config()->set('services.open_food_facts.user_id', 'wildforce');
    config()->set('services.open_food_facts.password', 'secret');
    Http::preventStrayRequests();
    Http::fake([
        'https://world.openfoodfacts.org/cgi/product_jqm2.pl' => Http::response([
            'status' => 'status not ok',
        ]),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->post('/api/nutrition/open-food-facts/contributions', contributionPayload(), [
        'Idempotency-Key' => 'open-food-facts-contribution-rejected',
    ])->assertStatus(502);
});

test('it returns 502 when Open Food Facts cannot be reached', function () {
    config()->set('services.open_food_facts.user_id', 'wildforce');
    config()->set('services.open_food_facts.password', 'secret');
    Http::preventStrayRequests();
    Http::fake([
        'https://world.openfoodfacts.org/cgi/product_jqm2.pl' => Http::failedConnection(),
    ]);

    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->post('/api/nutrition/open-food-facts/contributions', contributionPayload(), [
        'Idempotency-Key' => 'open-food-facts-contribution-unavailable',
    ])->assertStatus(502);
});

/** @return array<string, mixed> */
function contributionPayload(): array
{
    return [
        'barcode' => '8412345678901',
        'name' => 'Protein yogurt',
        'brands' => 'Wildforce',
        'macros_per_100g' => ['calories' => 95, 'protein' => 10, 'carbs' => 8, 'fat' => 2],
        'serving_size' => '160 g',
        'nutriscore' => 'a',
        'front_image' => UploadedFile::fake()->image('front.jpg'),
        'nutrition_image' => UploadedFile::fake()->image('nutrition.jpg'),
    ];
}
