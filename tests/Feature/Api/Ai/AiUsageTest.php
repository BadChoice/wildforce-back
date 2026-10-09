<?php

use App\Ai\Agents\Nutrition\MacrosFromTextAgent;
use App\Enums\CoachingEnrollmentStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionProvider;
use App\Enums\SubscriptionStatus;
use App\Models\AiUsage;
use App\Models\CoachingEnrollment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Ai\AgentModels;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\StructuredTextResponse;

beforeEach(function () {
    config()->set('ai-pricing.models', [
        'openai' => ['gpt-6-luna' => ['input' => 0.2, 'output' => 1.2, 'cache_read' => 0.02]],
    ]);
});

test('it records the token usage and cost of each generation for the requesting user without charging reasoning twice', function () {
    $user = User::factory()->create();
    MacrosFromTextAgent::fake([macrosResponseWithUsage(new TextUsage(inputTokens: 10_000, outputTokens: 2_000, cacheReadInputTokens: 4_000, reasoningTokens: 1_500))]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/nutrition-macros/generate', ['text' => 'An apple'], ['Idempotency-Key' => 'ai-usage-1'])
        ->assertOk();

    $usage = AiUsage::sole();

    expect($usage->user_id)->toBe($user->id)
        ->and($usage->agent)->toBe(MacrosFromTextAgent::class)
        ->and($usage->provider)->toBe('openai')
        ->and($usage->model)->toBe('gpt-6-luna')
        ->and($usage->input_tokens)->toBe(10_000)
        ->and($usage->output_tokens)->toBe(2_000)
        ->and($usage->cache_read_input_tokens)->toBe(4_000)
        ->and($usage->reasoning_tokens)->toBe(1_500)
        // 6,000 uncached × $0.20 + 4,000 cached × $0.02 + 2,000 output × $1.20 per million tokens; reasoning is already part of the output.
        ->and($usage->cost_micros)->toBe(1_200 + 80 + 2_400);
});

test('it records usage without a cost and logs an error when the model has no price', function () {
    config()->set('ai-pricing.models', []);
    Log::spy();
    $user = User::factory()->create();
    MacrosFromTextAgent::fake([macrosResponseWithUsage(new TextUsage(inputTokens: 1_000, outputTokens: 500))]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/nutrition-macros/generate', ['text' => 'An apple'], ['Idempotency-Key' => 'ai-usage-unpriced'])
        ->assertOk();

    expect(AiUsage::sole())->input_tokens->toBe(1_000)->cost_micros->toBeNull();
    Log::shouldHaveReceived('error')->once();
});

test('it rejects generations once the monthly budget is spent', function () {
    $user = User::factory()->create();
    AiUsage::factory()->for($user)->create(['cost_micros' => 2_000_000]);
    MacrosFromTextAgent::fake()->preventStrayPrompts();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/nutrition-macros/generate', ['text' => 'An apple'], ['Idempotency-Key' => 'ai-usage-over-budget'])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'ai_budget_exceeded')
        ->assertJsonPath('resets_at', now()->startOfMonth()->addMonth()->toIso8601String());

    MacrosFromTextAgent::assertNeverPrompted();
});

test('it does not count spend from previous months against the budget', function () {
    $user = User::factory()->create();
    AiUsage::factory()->for($user)->create(['cost_micros' => 2_000_000, 'created_at' => now()->startOfMonth()->subSecond()]);
    MacrosFromTextAgent::fake([macrosResponseWithUsage(new TextUsage)]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/nutrition-macros/generate', ['text' => 'An apple'], ['Idempotency-Key' => 'ai-usage-last-month'])
        ->assertOk();
});

test('it applies the budget to coached clients without their own subscription', function () {
    $client = User::factory()->create();
    $client->subscription()->update(['status' => SubscriptionStatus::Expired]);
    $coach = User::factory()->create();
    $coach->replaceSubscription(new Subscription([
        'plan' => SubscriptionPlan::CoachBasic,
        'provider' => SubscriptionProvider::Stripe,
        'status' => SubscriptionStatus::Active,
        'starts_at' => now()->subDay(),
        'renews_at' => now()->addMonth(),
    ]));
    CoachingEnrollment::create(['client_user_id' => $client->id, 'coach_user_id' => $coach->id, 'status' => CoachingEnrollmentStatus::Active, 'starts_at' => now()]);
    AiUsage::factory()->for($client)->create(['cost_micros' => SubscriptionPlan::CoachedExternal->monthlyAiBudgetInMicros()]);

    $this->actingAs($client, 'sanctum')
        ->postJson('/api/nutrition-macros/generate', ['text' => 'An apple'], ['Idempotency-Key' => 'ai-usage-coached'])
        ->assertTooManyRequests()
        ->assertJsonPath('code', 'ai_budget_exceeded');
});

test('it rejects generations for users with AI blocked', function () {
    $user = User::factory()->create();
    $user->forceFill(['ai_blocked_at' => now()])->save();
    MacrosFromTextAgent::fake()->preventStrayPrompts();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/nutrition-macros/generate', ['text' => 'An apple'], ['Idempotency-Key' => 'ai-usage-blocked'])
        ->assertForbidden()
        ->assertJsonPath('code', 'ai_blocked');

    MacrosFromTextAgent::assertNeverPrompted();
});

test('every model used by an agent has a price', function () {
    $pricedModels = require config_path('ai-pricing.php');

    foreach (app(AgentModels::class)->all() as ['provider' => $provider, 'model' => $model]) {
        expect($pricedModels['models'][$provider][$model] ?? null)
            ->not->toBeNull("{$provider}:{$model} has no price. Run `php artisan app:sync-ai-pricing`.");
    }
});

function macrosResponseWithUsage(TextUsage $usage): StructuredTextResponse
{
    $structured = [
        'title' => 'Apple',
        'estimatedFoods' => [[
            'name' => 'Apple',
            'canonicalFoodName' => 'apple',
            'amountGrams' => 180,
            'estimatedMacros' => ['calories' => 94, 'protein' => 0, 'carbs' => 25, 'fat' => 0],
        ]],
        'estimatedMacros' => ['calories' => 94, 'protein' => 0, 'carbs' => 25, 'fat' => 0],
        'analysisNotes' => null,
    ];

    return new StructuredTextResponse($structured, json_encode($structured), $usage, new Meta('openai', 'gpt-6-luna'));
}
