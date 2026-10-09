<?php

namespace App\Services\Ai;

use Laravel\Ai\Responses\Data\TextUsage;

final class AiCostCalculator
{
    /**
     * Prices in config/ai-pricing.php are USD per million tokens, so tokens multiplied by them gives millionths of a dollar.
     * Returns null when the model has no configured price.
     */
    public function costInMicros(string $provider, string $model, TextUsage $usage): ?int
    {
        $prices = config('ai-pricing.models')[$provider][$model] ?? null;

        if ($prices === null) {
            return null;
        }

        $cost = $usage->uncachedInputTokens() * $prices['input']
            + ($usage->cacheReadInputTokens ?? 0) * ($prices['cache_read'] ?? $prices['input'])
            + ($usage->cacheWriteInputTokens ?? 0) * ($prices['cache_write'] ?? $prices['input'])
            + $usage->outputTokens * $prices['output'];

        return (int) round($cost);
    }
}
