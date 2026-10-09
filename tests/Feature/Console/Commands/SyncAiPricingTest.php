<?php

use App\Console\Commands\SyncAiPricing;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->path = tempnam(sys_get_temp_dir(), 'ai-pricing');
    config()->set('ai-pricing.models', []);
    Http::preventStrayRequests();
});

afterEach(function () {
    @unlink($this->path);
});

test('it writes per million token prices for every agent model and requested model', function () {
    Http::fake([SyncAiPricing::SOURCE_URL => Http::response([
        'gpt-6-luna' => ['litellm_provider' => 'openai', 'input_cost_per_token' => 2e-07, 'output_cost_per_token' => 1.2e-06, 'cache_read_input_token_cost' => 2e-08],
        'azure/gpt-6-luna' => ['litellm_provider' => 'azure', 'input_cost_per_token' => 9e-07, 'output_cost_per_token' => 9e-06],
        'anthropic/claude-haiku-4-5' => ['litellm_provider' => 'anthropic', 'input_cost_per_token' => 1e-06, 'output_cost_per_token' => 5e-06],
    ])]);

    $this->artisan('app:sync-ai-pricing', ['--model' => ['anthropic:claude-haiku-4-5'], '--path' => $this->path])
        ->assertSuccessful();

    expect((require $this->path)['models'])->toBe([
        'anthropic' => ['claude-haiku-4-5' => ['input' => 1.0, 'output' => 5.0]],
        'openai' => ['gpt-6-luna' => ['input' => 0.2, 'output' => 1.2, 'cache_read' => 0.02]],
    ]);
});

test('it fails and keeps existing prices when a model is missing from the list', function () {
    config()->set('ai-pricing.models', ['openai' => ['gpt-legacy' => ['input' => 1.0, 'output' => 2.0]]]);
    Http::fake([SyncAiPricing::SOURCE_URL => Http::response([
        'gpt-6-luna' => ['litellm_provider' => 'openai', 'input_cost_per_token' => 2e-07, 'output_cost_per_token' => 1.2e-06],
    ])]);

    $this->artisan('app:sync-ai-pricing', ['--path' => $this->path])
        ->expectsOutputToContain('No price found for openai:gpt-legacy')
        ->assertFailed();

    expect((require $this->path)['models']['openai'])->toBe([
        'gpt-6-luna' => ['input' => 0.2, 'output' => 1.2],
        'gpt-legacy' => ['input' => 1.0, 'output' => 2.0],
    ]);
});

test('it leaves the pricing file untouched when the list cannot be downloaded', function () {
    file_put_contents($this->path, 'original');
    Http::fake([SyncAiPricing::SOURCE_URL => Http::response(status: 500)]);

    $this->artisan('app:sync-ai-pricing', ['--path' => $this->path])->assertFailed();

    expect(file_get_contents($this->path))->toBe('original');
});
