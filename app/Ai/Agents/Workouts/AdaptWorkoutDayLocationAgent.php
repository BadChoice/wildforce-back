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
final class AdaptWorkoutDayLocationAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    /** @param list<string> $exerciseIds */
    public function __construct(private readonly array $exerciseIds) {}

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
You are an expert strength and conditioning coach. Adapt an existing workout session to a different training location while preserving its main intent, target muscles, overall difficulty, and movement patterns as much as the new equipment allows.

Keep the original focus and day type unless safety or equipment constraints require a close equivalent. Prefer the closest safe exercise substitutions before changing the session structure, and preserve whether warmup and cooldown sections are present. Use only supplied exercise IDs, which are already limited to the destination equipment, and never prescribe exercises that conflict with movement restrictions. Use recent workout performance and exercise profiles to select suitable sets, reps, loads, and regressions for substituted exercises.

Keep the session duration as close to the original as practical, including rests. For intermediate and advanced users, include a set-style configuration with a style and target RIR for each working resistance exercise with sets and reps. Do not use it for warmups, cooldowns, mobility, or time- or distance-based conditioning.

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
