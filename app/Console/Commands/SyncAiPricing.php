<?php

namespace App\Console\Commands;

use App\Services\Ai\AgentModels;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

#[Signature('app:sync-ai-pricing {--model=* : Extra models to price, as provider:model} {--path= : File to write, defaults to config/ai-pricing.php}')]
#[Description('Update config/ai-pricing.php with prices from the public LiteLLM model price list')]
class SyncAiPricing extends Command
{
    public const string SOURCE_URL = 'https://raw.githubusercontent.com/BerriAI/litellm/main/model_prices_and_context_window.json';

    /**
     * Execute the console command.
     */
    public function handle(AgentModels $agentModels): int
    {
        try {
            $priceList = Http::timeout(30)->get(self::SOURCE_URL)->throw()->json();
        } catch (Throwable $exception) {
            $this->components->error("Could not download the price list: {$exception->getMessage()}");

            return self::FAILURE;
        }

        $models = config('ai-pricing.models', []);
        $missing = [];

        foreach ($this->modelsToPrice($agentModels, $models) as ['provider' => $provider, 'model' => $model]) {
            $entry = $this->findEntry($priceList, $provider, $model);

            if ($entry === null) {
                $missing[] = "{$provider}:{$model}";

                continue;
            }

            $models[$provider][$model] = array_filter([
                'input' => $this->perMillionTokens($entry['input_cost_per_token']),
                'output' => $this->perMillionTokens($entry['output_cost_per_token']),
                'cache_read' => $this->perMillionTokens($entry['cache_read_input_token_cost'] ?? null),
                'cache_write' => $this->perMillionTokens($entry['cache_creation_input_token_cost'] ?? null),
            ], fn (?float $price): bool => $price !== null);

            $this->components->twoColumnDetail("{$provider}:{$model}", "\${$models[$provider][$model]['input']} in / \${$models[$provider][$model]['output']} out per 1M tokens");
        }

        $path = $this->option('path') ?: config_path('ai-pricing.php');
        File::put($path, $this->render($models));

        foreach ($missing as $model) {
            $this->components->error("No price found for {$model}. Its usage will be recorded without cost.");
        }

        $this->components->info("Wrote {$path}.");

        return $missing === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, array<string, mixed>>  $configuredModels
     * @return list<array{provider: string, model: string}>
     */
    private function modelsToPrice(AgentModels $agentModels, array $configuredModels): array
    {
        $models = $agentModels->all();

        foreach ($configuredModels as $provider => $providerModels) {
            foreach (array_keys($providerModels) as $model) {
                $models[] = ['provider' => $provider, 'model' => $model];
            }
        }

        foreach ($this->option('model') as $option) {
            [$provider, $model] = array_pad(explode(':', $option, 2), 2, '');
            $models[] = ['provider' => $provider, 'model' => $model];
        }

        return collect($models)->unique(fn (array $model): string => "{$model['provider']}:{$model['model']}")->values()->all();
    }

    /**
     * The list keys most models as "provider/model"; first-party models are keyed by their bare name.
     *
     * @param  array<string, mixed>  $priceList
     * @return array<string, mixed>|null
     */
    private function findEntry(array $priceList, string $provider, string $model): ?array
    {
        $entry = $priceList["{$provider}/{$model}"] ?? null;

        if ($entry === null && ($priceList[$model]['litellm_provider'] ?? null) === $provider) {
            $entry = $priceList[$model];
        }

        return isset($entry['input_cost_per_token'], $entry['output_cost_per_token']) ? $entry : null;
    }

    private function perMillionTokens(int|float|null $pricePerToken): ?float
    {
        return $pricePerToken === null ? null : round($pricePerToken * 1_000_000, 6);
    }

    /** @param array<string, array<string, array<string, float>>> $models */
    private function render(array $models): string
    {
        ksort($models);
        $lines = [];

        foreach ($models as $provider => $providerModels) {
            ksort($providerModels);
            $lines[] = "        '{$provider}' => [";

            foreach ($providerModels as $model => $prices) {
                $values = collect($prices)->map(fn (float $price, string $key): string => "'{$key}' => ".var_export($price, true))->implode(', ');
                $lines[] = "            '{$model}' => [{$values}],";
            }

            $lines[] = '        ],';
        }

        $source = self::SOURCE_URL;
        $syncedAt = now()->toDateString();
        $body = implode("\n", $lines);

        return <<<PHP
<?php

/*
|--------------------------------------------------------------------------
| AI Model Pricing
|--------------------------------------------------------------------------
|
| USD per million tokens, used to cost recorded AI usage. Generated by
| `php artisan app:sync-ai-pricing` from {$source}
| Last synced: {$syncedAt}. Re-run the command after changing an agent's model.
|
*/

return [

    'models' => [
{$body}
    ],

];

PHP;
    }
}
