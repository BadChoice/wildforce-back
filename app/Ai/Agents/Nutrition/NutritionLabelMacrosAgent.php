<?php

namespace App\Ai\Agents\Nutrition;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider('openai')]
#[Model('gpt-6-luna')]
class NutritionLabelMacrosAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'INSTRUCTIONS'
        You are an expert at reading food nutrition labels.
        The user will provide the OCR text of a nutrition label, laid out as a table that keeps the label's rows and columns.
        Your task is to extract the macronutrients per 100 grams (or 100 ml) as accurately as possible.

        Rules:
        - Always use the per 100 g / 100 ml column, never the per serving column.
        - Return calories in kilocalories. Labels often show energy in both kJ and kcal; use the kcal value only.
        - Never return a kilojoule value as calories and never convert between kJ and kcal yourself. The kJ number is always the larger one (about 4.18 times the kcal value); ignore it completely.
        - Return protein, carbs, and fat in grams. Carbs are total carbohydrates, not only sugars; fat is total fat, not only saturates.
        - Correct obvious OCR errors, for example a comma used as a decimal separator, `O` read instead of `0`, or a missing decimal point.
        - Do not invent values. If a value is not on the label, return 0 for it.
        - If the label states a serving size, return it as written (for example `30 g`); otherwise return null.

        Return only structured data matching the schema.
    INSTRUCTIONS;
    }

    public function providerOptions(Lab|string $provider): array
    {
        return match ($provider) {
            Lab::OpenAI => [
                'reasoning' => ['effort' => 'low'],
            ],
            default => [],
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'servingSize' => $schema->string()->nullable()->description('The serving size as written on the label, or null'),
            'macros' => $schema->object(fn (JsonSchema $schema): array => [
                'calories' => $schema->number()->min(0)->required()->description('Kilocalories per 100 g'),
                'protein' => $schema->number()->min(0)->required()->description('Grams per 100 g'),
                'carbs' => $schema->number()->min(0)->required()->description('Grams per 100 g'),
                'fat' => $schema->number()->min(0)->required()->description('Grams per 100 g'),
            ])->required(),
        ];
    }
}
