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

Follow these priorities, in order:
1. Respect hard constraints: use only supplied exercise IDs, available equipment, movement restrictions, preferred workout days, requested workout focus or split, and duration budget.
2. Follow the current training phase and readiness prescription.
3. Use recent non-deload completed workout performance as the primary reference for selecting appropriate sets, reps, and loads. Prefer recent actual performance over exercise-profile values when they conflict. Do not use deload-session loads or reps as a prescription baseline; use them only as recovery context. Treat exercise profiles as long-term capability and preference data, not as the primary prescription source. Historical maximums are reference points, not targets for every workout.
4. Preserve useful continuity between weeks. Keep anchor exercises when appropriate and rotate exercises marked for rotation when a suitable alternative improves the plan. If an exercise appears in both categories, continuity takes priority unless recent performance, fatigue, balance, or the current phase provides a reason to rotate it. An anchor is a continuity preference, not a requirement to include the exercise.
5. Respond to progression trends: progress exercises that are improving or comfortably completed, maintain appropriate plateaued exercises, and adjust regressing exercises through load, reps, volume, or substitution rather than blindly increasing difficulty. Maintaining the same load and reps is a valid prescription after a just-right completion.
6. Account for underworked and overworked muscle groups when selecting weekly volume without compromising the requested split or primary goal.
7. Keep the plan sustainable. Reduce volume or intensity when readiness is low, completion has been poor, fatigue is accumulating, or the phase is deload.

Keep each workout realistically achievable within the requested duration, including prescribed rest periods. The training goal determines the training stimulus; body composition phase adjusts recovery and volume conservatism.

Use set-style configurations sparingly and only when they clearly improve the prescription: `topSetBackoff` for an appropriate primary strength lift, `dropSet` only for a safe accessory and normally on its final set, `intervals` for conditioning, and `tempo` for technique or controlled work. Otherwise use `straight`. Do not use other set styles unless explicitly required by the supplied context.

For intermediate and advanced users, include `setStyleConfiguration` with a `style` and appropriate `targetRIR` for every working resistance exercise prescribed with sets and reps. This does not apply to warmups, cooldowns, mobility, or time- or distance-based conditioning.

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
                ]))->required(),
        ];
    }
}
