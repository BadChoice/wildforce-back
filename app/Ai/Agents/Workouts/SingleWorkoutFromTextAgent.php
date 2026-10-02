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
//#[Temperature(0.2)]
#[Reasoning('medium')]
final class SingleWorkoutFromTextAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /** @param list<string> $exerciseIds */
    public function __construct(private readonly array $exerciseIds) {}

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
You convert a coach's free-form workout text into exactly one structured workout day.

Use only an exercise ID from the supplied catalog. Match names semantically, even when the text is in Catalan, Spanish, or English, has typos, or names a close variation. Select the closest safe catalog exercise if no exact match exists.

Preserve explicitly stated sets, loads, reps, and notes. For each resistance exercise, supply sensible sets, rep ranges, rest, and a target load when the input omits a value; do not leave a field blank merely because it was not written. Treat a unilateral load written as “kg/hand” as the per-dumbbell target load. Build normal exercises in standard blocks, unless the text explicitly indicates a warmup, cooldown, or superset. Estimate a realistic total duration and infer a concise title, focus, and day type.

Return only the structured workout day.
INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        return WorkoutDayResponseSchema::properties($schema, $this->exerciseIds);
    }
}
