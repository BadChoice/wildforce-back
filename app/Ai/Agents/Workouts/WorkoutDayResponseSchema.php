<?php

namespace App\Ai\Agents\Workouts;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;

final class WorkoutDayResponseSchema
{
    /**
     * @param  list<string>  $exerciseIds
     * @return array<string, Type>
     */
    public static function properties(JsonSchema $schema, array $exerciseIds): array
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
                    'exercise' => $schema->string()->enum($exerciseIds)->required(),
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
                        'style' => $schema->string()->enum(['warmup', 'straight', 'topSetBackoff', 'ascendingPyramid', 'dropSet', 'restPause', 'intervals', 'tempo'])->required(),
                        'applies_to_final_set_only' => $schema->boolean(),
                        'drop_count' => $schema->integer()->min(1),
                        'drop_weight_percent' => $schema->number()->min(1)->max(100),
                        'backoff_set_count' => $schema->integer()->min(1),
                        'backoff_weight_percent' => $schema->number()->min(1)->max(100),
                        'intra_set_rest_seconds' => $schema->integer()->min(1),
                        'tempo' => $schema->string(),
                        'target_rir' => $schema->integer()->min(0)->max(10),
                    ]),
                    'notes' => $schema->string(),
                ]))->required(),
            ]))->required(),
        ];
    }
}
