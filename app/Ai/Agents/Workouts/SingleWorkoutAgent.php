<?php

namespace App\Ai\Agents\Workouts;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

#[Provider('openai')]
#[Model('gpt-5.6-luna')]
#[Temperature(0.2)]
final class SingleWorkoutAgent implements Agent, HasStructuredOutput
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

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'focus' => $schema->string()->enum(['fullBody', 'upperBody', 'lowerBody', 'chest', 'back', 'shoulders', 'arms', 'core', 'cardio', 'mobility'])->required(),
            'dayType' => $schema->string()->enum(['strength', 'hypertrophy', 'technique', 'volume', 'deload', 'recovery', 'conditioning'])->required(),
            'estimatedDurationMinutes' => $schema->integer()->min(1)->required(),
            'notes' => $schema->string(),
            'blocks' => $schema->array()->min(1)->items($schema->object(fn (JsonSchema $schema): array => [
                'type' => $schema->string()->enum(['warmup', 'standard', 'superset', 'cooldown'])->required(),
                'rounds' => $schema->integer()->min(1)->required(),
                'restAfterBlockSeconds' => $schema->integer()->min(0),
                'notes' => $schema->string(),
                'exercises' => $schema->array()->min(1)->items($schema->object(fn (JsonSchema $schema): array => [
                    'exercise' => $schema->string()->enum($this->exerciseIds)->required(),
                    'sets' => $schema->integer()->min(1), 'repsMin' => $schema->integer()->min(1), 'repsMax' => $schema->integer()->min(1),
                    'targetReps' => $schema->array()->items($schema->integer()->min(1)), 'targetWeightKg' => $schema->number()->min(0), 'targetWeightsKg' => $schema->array()->items($schema->number()->min(0)),
                    'targetDurationMinutes' => $schema->integer()->min(1), 'targetDurationSeconds' => $schema->integer()->min(1), 'targetDistanceKm' => $schema->number()->min(0), 'targetPaceSecondsPerKm' => $schema->integer()->min(1), 'restSeconds' => $schema->integer()->min(0),
                    'setStyleConfiguration' => $schema->object(fn (JsonSchema $schema): array => [
                        'style' => $schema->string()->enum(['warmup', 'straight', 'topSetBackoff', 'ascendingPyramid', 'dropSet', 'restPause', 'intervals', 'tempo'])->required(),
                        'applies_to_final_set_only' => $schema->boolean(), 'drop_count' => $schema->integer()->min(1), 'drop_weight_percent' => $schema->number()->min(1)->max(100),
                        'backoff_set_count' => $schema->integer()->min(1), 'backoff_weight_percent' => $schema->number()->min(1)->max(100), 'intra_set_rest_seconds' => $schema->integer()->min(1), 'tempo' => $schema->string(), 'target_rir' => $schema->integer()->min(0)->max(10),
                    ]),
                    'notes' => $schema->string(),
                ]))->required(),
            ]))->required(),
        ];
    }
}
