<?php

namespace App\Ai\Agents\Workouts;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider('openai')]
#[Model('gpt-5.6-luna')]

final class WorkoutPlanGeneratorAgent implements Agent, HasProviderOptions, HasStructuredOutput
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
2. Follow the phase prescription (sets, reps, target RIR, rest, volume, and load guidance) and adjust it for readiness.
3. Use recent non-deload completed workout performance as the primary reference for selecting appropriate sets, reps, and loads. Prefer recent actual performance over exercise-profile values when they conflict. Do not use deload-session loads or reps as a prescription baseline; use them only as recovery context. Treat exercise profiles as long-term capability and preference data, not as the primary prescription source. Historical maximums are reference points, not targets for every workout.
4. Follow each exercise's status. `progress`: keep the exercise and add load or reps. `keep`: keep the exercise; maintaining the same load and reps is a valid prescription after a just-right completion. `reduce`: keep it with 5-10% less load, or swap it for an easier variation. `rotate`: replace it with an alternative that trains the same movement pattern. Do not replace listed exercises with another status unless a hard constraint or the requested split requires it.
5. Account for underworked and overworked muscle groups when selecting weekly volume without compromising the requested split or primary goal.
6. Keep the plan sustainable. Reduce volume or intensity when readiness is low, completion has been poor, fatigue is accumulating, or the phase is deload.

Keep each workout realistically achievable within the requested duration, including prescribed rest periods. The training goal determines the training stimulus; body composition phase adjusts recovery and volume conservatism.

Warmups, only when requested: start the day with a `warmup` block of ramp-up sets for the first main lift, plus the second main lift when it uses a different movement pattern. Each ramp-up entry repeats the main lift with style `warmup`, 2-4 sets of decreasing reps (for example 8, 5, 3) in `targetReps`, loads rising from about 40% to 80% of that day's working load in `targetWeightsKg`, and 60 s rest. A bodyweight lift gets 1-2 easier sets or an easier variation instead. Optionally precede them with 3-5 minutes of light cardio. Use mobility drills in warmups only for a mobility goal or focus. Ramp-up sets do not count toward working volume, but count toward the duration budget.

Cooldowns, only when requested: about 5 minutes of light cardio, mobility, or stretches for the muscles trained that day.

Use set-style configurations sparingly and only when they clearly improve the prescription: `warmup` only for ramp-up sets, `topSetBackoff` for an appropriate primary strength lift, `dropSet` only for a safe accessory and normally on its final set, `intervals` for conditioning, and `tempo` for technique or controlled work. Otherwise use `straight`. Do not use other set styles unless explicitly required by the supplied context.

For intermediate and advanced users, include `setStyleConfiguration` with a `style` and appropriate `targetRIR` for every working resistance exercise prescribed with sets and reps. This does not apply to warmups, cooldowns, mobility, or time- or distance-based conditioning.

Return only the structured plan. Do not include medical advice or prose outside the structured response.
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
                    ...WorkoutDayResponseSchema::properties($schema, $this->exerciseIds),
                    'intendedWeekday' => $schema->string()->enum($this->workoutDays)->required(),
                ]))->required(),
        ];
    }
}
