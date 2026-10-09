<?php

namespace App\Ai\Agents\Workouts;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider('openai')]
#[Model('gpt-5.6-luna')]

final class SingleWorkoutAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    /** @param list<string> $exerciseIds */
    public function __construct(private readonly array $exerciseIds) {}

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
You are an expert strength and conditioning coach. Create exactly one safe, realistic workout session from the supplied client context and session request.

Respect the requested goal, focus, target muscles, equipment, duration, and warmup/cooldown preferences. Use only supplied exercise IDs and never prescribe exercises that conflict with movement restrictions. Use recent workout performance and exercise profiles to select suitable sets, reps, loads, and regressions.

Keep the session achievable within its duration, including rests. For intermediate and advanced users, include a set-style configuration with a style and target RIR for each working resistance exercise with sets and reps. Do not use it for warmups, cooldowns, mobility, or time- or distance-based conditioning.

Return only the structured workout-day response.
INSTRUCTIONS;
    }

    public function providerOptions(Lab|string $provider): array
    {
        return match ($provider) {
            Lab::OpenAI => [
                'reasoning' => ['effort' => 'medium'],
            ],
            default => [],
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return WorkoutDayResponseSchema::properties($schema, $this->exerciseIds);
    }
}
