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
#[Model('gpt-5.6-luna')]
#[Reasoning('low')]
class MacrosFromTextAgent implements Agent, HasStructuredOutput, HasProviderOptions
{

    use Promptable;

    public function __construct() {}

    public function instructions(): string {
        return <<<INSTRUCTIONS
        You are an expert nutrition coach.
        The user will provide a text description of what they ate.
        Your task is to analyze the description and estimate the meal's macronutrients and kilocalories.

        The response must be in \(language).
        Write the `title`, `name`, and `analysisNotes` fields in \(language).
        Write `canonicalFoodName` in English as a stable food lookup term.

        Rules:
        - Estimate the foods described in the text as realistically as possible.
        - Every estimated food must include a `canonicalFoodName` that is concise, singular, and easy to search in a nutrition database.
        - `canonicalFoodName` should avoid brand names and decorative wording. Keep preparation details only when they materially change the food, for example `white rice, cooked` or `chicken breast, grilled`.
        - Every estimated food must include a realistic weight in grams using the `amountGrams` field.
        - Use grams only for food quantities, never cups, spoons, servings, or vague amounts.
        - Return calories in kilocalories and protein, carbs, and fat in grams.
        - Return the total estimated calories, protein, carbs, and fat for the full meal.
        - Keep `analysisNotes` concise and mention that the values are estimates based on the description.
        - Ensure the totalEstimatedMacros approximately equal the sum of all estimatedFoods macros.

        If the description is vague, make the best estimate you can based on typical portion sizes and ingredients.

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
        $macros = fn(JsonSchema $schema): array => [
            'calories' => $schema->number()->min(0)->required(),
            'protein' => $schema->number()->min(0)->required(),
            'carbs' => $schema->number()->min(0)->required(),
            'fat' => $schema->number()->min(0)->required(),
        ];

        return [
            'title' => $schema->string()->description("A user facing title of the analysed food. Ex: Fish with Chips")->required(),
            'estimatedFoods' => $schema->array()->items($schema->object(fn (JsonSchema $schema): array => [
                "name" => $schema->string()->required(),
                "canonicalFoodName"=> $schema->string()->required()->description("English canonical lookup name for this food"),
                "amountGrams"=> $schema->integer()->required(),
                "estimatedMacros"=> $macros,
            ]))->required(),
            'estimatedMacros' => $macros,
            'analysisNotes' =>  $schema->string()->nullable()
    }
}