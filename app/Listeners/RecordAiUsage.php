<?php

namespace App\Listeners;

use App\Models\AiUsage;
use App\Services\Ai\AiCostCalculator;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Events\AgentPrompted;

class RecordAiUsage
{
    public function __construct(private readonly AiCostCalculator $costCalculator) {}

    /**
     * Usage is attributed to the authenticated user who triggered the call, e.g. the coach when generating from the dashboard.
     */
    public function handle(AgentPrompted $event): void
    {
        $provider = $event->prompt->provider->name();
        $model = $event->prompt->model;
        $usage = $event->response->usage;
        $cost = $this->costCalculator->costInMicros($provider, $model, $usage);

        if ($cost === null) {
            Log::error('AI usage recorded without a price. Run `php artisan app:sync-ai-pricing` to add it.', ['provider' => $provider, 'model' => $model]);
        }

        AiUsage::create([
            'user_id' => auth()->id(),
            'invocation_id' => $event->invocationId,
            'agent' => $event->prompt->agent::class,
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => $usage->inputTokens,
            'output_tokens' => $usage->outputTokens,
            'cache_read_input_tokens' => $usage->cacheReadInputTokens,
            'cache_write_input_tokens' => $usage->cacheWriteInputTokens,
            'reasoning_tokens' => $usage->reasoningTokens,
            'cost_micros' => $cost,
        ]);
    }
}
