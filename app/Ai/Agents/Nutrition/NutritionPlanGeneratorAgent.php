<?php

namespace App\Ai\Agents\Nutrition;

use App\Enums\Goal;
use App\Enums\WorkoutFocus;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

#[Provider('openai')]
#[Model('gpt-5.6-luna')]
#[Effort('medium')]
final class NutritionPlanGeneratorAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function __construct(private readonly bool $wantsMealSuggestions) {}

    public function instructions(): string
    {
        $mealSuggestionRule = $this->wantsMealSuggestions
            ? 'Provide practical meal suggestions with specific food quantities in grams when they support the daily targets.'
            : 'Do not provide meal suggestions; return an empty `meals` array for every day.';

        return <<<INSTRUCTIONS
You are an expert sports nutrition planner. Create a safe, practical seven-day nutrition plan aligned with the supplied client context, training schedule, body-composition goal, and food preferences.

Follow these priorities, in order:
1. Treat the supplied daily calorie and macro targets as deterministic constraints. Echo them exactly; never recalculate or change them.
2. Respect dietary style, allergies, intolerances, excluded foods, dislikes, budget, cooking-effort, and eating-window preferences.
3. Keep protein supportively high, place more carbohydrates around demanding training days, and avoid extreme surpluses or deficits.
4. Use the client’s preferred language for all user-facing notes, meal titles, guidance, and example foods.
5. Keep the plan concise and sustainable. This is a planning layer, not a recipe book.

{$mealSuggestionRule}
INSTRUCTIONS;
    }

    public function schema(JsonSchema $schema): array
    {
        $macros = fn (JsonSchema $schema): array => [
            'calories' => $schema->number()->min(0)->required(),
            'protein' => $schema->number()->min(0)->required(),
            'carbs' => $schema->number()->min(0)->required(),
            'fat' => $schema->number()->min(0)->required(),
        ];

        return [
            'startsOn' => $schema->string()->format('date')->required(),
            'goal' => $schema->string()->enum(Goal::allCasesArray())->required(),
            'bodyCompositionPhase' => $schema->string()->enum(['bulk', 'cut', 'maintain'])->required(),
            'dailyCalorieAverage' => $schema->number()->min(0)->required(),
            'notes' => $schema->string(),
            'days' => $schema->array()->min(7)->max(7)->items($schema->object(fn (JsonSchema $schema): array => [
                'date' => $schema->string()->format('date')->required(),
                'weekday' => $schema->string()->enum(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])->required(),
                'dayType' => $schema->string()->enum(['training', 'rest', 'recovery'])->required(),
                'targetMacros' => $schema->object($macros)->required(),
                'plannedWorkoutTitle' => $schema->string(),
                'plannedWorkoutFocus' => $schema->string()->enum(WorkoutFocus::allCasesArray()),
                'energyDemand' => $schema->string()->enum(['low', 'medium', 'high'])->required(),
                'notes' => $schema->string(),
                'preWorkoutGuidance' => $schema->string(),
                'postWorkoutGuidance' => $schema->string(),
                'meals' => $schema->array()->items($schema->object(fn (JsonSchema $schema): array => [
                    'title' => $schema->string()->required(),
                    'orderIndex' => $schema->integer()->min(0)->required(),
                    'mealType' => $schema->string()->enum(['breakfast', 'lunch', 'snack', 'dinner'])->required(),
                    'targetMacros' => $schema->object($macros)->required(),
                    'guidance' => $schema->string(),
                    'exampleFoods' => $schema->array()->items($schema->object(fn (JsonSchema $schema): array => [
                        'name' => $schema->string()->required(),
                        'amountGrams' => $schema->integer()->min(1)->required(),
                    ]))->required(),
                ]))->required(),
            ]))->required(),
        ];
    }
}
