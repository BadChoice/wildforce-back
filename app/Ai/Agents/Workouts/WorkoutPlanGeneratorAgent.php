<?php

namespace App\Ai\Agents\Workouts;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

#[Provider('openai')]
#[Model('gpt-5.6-luna')]
#[Temperature(0.2)]
final class WorkoutPlanGeneratorAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  list<string>  $exerciseIds
     * @param  list<string>  $workoutDays
     */
    public function __construct(
        private readonly array $exerciseIds,
        private readonly array $workoutDays,
    ) {}

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
You are an expert strength and conditioning coach. Create a safe, realistic one-week workout plan from the supplied client context.

Use only the supplied exercise IDs. Respect the client's available equipment, movement restrictions, schedule, duration budget, and progression analysis. Keep the plan sustainable: preserve exercises marked as anchors, replace exercises marked for rotation, and reduce volume or intensity when readiness is low or the phase is deload.

Use set-style configurations only when they add value: `topSetBackoff` for a primary strength lift, `dropSet` for a safe accessory's final set, `intervals` for conditioning, and `tempo` for technique or controlled work. Otherwise use straightforward sets and omit the configuration.

Return only the structured plan. Do not include medical advice or prose outside the structured response.
INSTRUCTIONS;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'notes' => $schema->string(),
            'workoutDays' => $schema->array()
                ->min(count($this->workoutDays))
                ->max(count($this->workoutDays))
                ->items($schema->object(fn (JsonSchema $schema): array => [
                    'title' => $schema->string()->required(),
                    'focus' => $schema->string()->enum([
                        'fullBody', 'upperBody', 'lowerBody', 'chest', 'back', 'shoulders', 'arms', 'core', 'cardio', 'mobility',
                    ])->required(),
                    'intendedWeekday' => $schema->string()->enum($this->workoutDays)->required(),
                    'dayType' => $schema->string()->enum([
                        'strength', 'hypertrophy', 'technique', 'volume', 'deload', 'recovery', 'conditioning',
                    ])->required(),
                    'estimatedDurationMinutes' => $schema->integer()->min(1)->required(),
                    'notes' => $schema->string(),
                    'blocks' => $schema->array()->min(1)->items($schema->object(fn (JsonSchema $schema): array => [
                        'type' => $schema->string()->enum(['warmup', 'standard', 'superset', 'cooldown'])->required(),
                        'rounds' => $schema->integer()->min(1)->required(),
                        'restAfterBlockSeconds' => $schema->integer()->min(0),
                        'notes' => $schema->string(),
                        'exercises' => $schema->array()->min(1)->items($schema->object(fn (JsonSchema $schema): array => [
                            'exercise' => $schema->string()->enum($this->exerciseIds)->required(),
                            'sets' => $schema->integer()->min(1),
                            'repsMin' => $schema->integer()->min(1),
                            'repsMax' => $schema->integer()->min(1),
                            'targetReps' => $schema->array()->items($schema->integer()->min(1)),
                            'targetWeightKg' => $schema->number()->min(0),
                            'targetWeightsKg' => $schema->array()->items($schema->number()->min(0)),
                            'targetDurationMinutes' => $schema->integer()->min(1),
                            'targetDurationSeconds' => $schema->integer()->min(1),
                            'targetDistanceKm' => $schema->number()->min(0),
                            'targetPaceSecondsPerKm' => $schema->integer()->min(1),
                            'restSeconds' => $schema->integer()->min(0),
                            'setStyleConfiguration' => $schema->object(fn (JsonSchema $schema): array => [
                                'style' => $schema->string()->enum([
                                    'warmup', 'straight', 'topSetBackoff', 'ascendingPyramid', 'dropSet', 'restPause', 'intervals', 'tempo',
                                ])->required(),
                                'appliesToFinalSetOnly' => $schema->boolean(),
                                'dropCount' => $schema->integer()->min(1),
                                'dropWeightPercent' => $schema->number()->min(1)->max(100),
                                'backoffSetCount' => $schema->integer()->min(1),
                                'backoffWeightPercent' => $schema->number()->min(1)->max(100),
                                'intraSetRestSeconds' => $schema->integer()->min(1),
                                'tempo' => $schema->string(),
                                'targetRIR' => $schema->integer()->min(0)->max(10),
                            ]),
                            'notes' => $schema->string(),
                        ]))->required(),
                    ]))->required(),
                ]))->required(),
        ];
    }
}
