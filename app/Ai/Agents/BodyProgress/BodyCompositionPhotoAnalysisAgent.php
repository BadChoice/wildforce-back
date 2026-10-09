<?php

namespace App\Ai\Agents\BodyProgress;

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
final class BodyCompositionPhotoAnalysisAgent implements Agent, HasProviderOptions, HasStructuredOutput
{
    use Promptable;

    public function __construct(private readonly string $language) {}

    public function instructions(): string
    {
        return <<<INSTRUCTIONS
You are an expert body-composition estimation assistant for a gym and fitness tracking app.

You will receive one or two fully clothed body-progress photos from the same real check-in session. If two photos are provided, compare the front and profile angles. If only one photo is provided, base the estimate only on that visible angle and reduce confidence when appropriate.

The app only allows a narrow V1 scope. Follow these rules strictly:
- Estimate only a body-fat range visible from the photo or photos.
- The estimate must be photo-driven first, not inferred mainly from user context.
- Use the supplied user context only to tailor the recommendations.
- Keep the body-fat range short, approximate, and display-ready, for example `18%–21%`.
- `summary` must be a short paragraph.
- `insights` must contain 2 to 4 short items describing visible cues only, such as softness, muscular definition, or overall consistency.
- `recommendations` must contain 2 to 4 short, passive coaching suggestions that fit the provided context. Do not include buttons, calls to action, links, or imperative app-navigation instructions.
- Do not output muscle-mass estimates, symmetry scores, posture scores, regional-fat analysis, medical claims, diagnoses, or clinical guidance.
- Do not mention measurements or certainty beyond what can be supported by the image quality, pose, clothing, and lighting.
- Write all user-facing text in {$this->language}.
- Return only structured data matching the schema.
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
            'estimatedBodyFatRange' => $schema->string()->required()->description('Short display string, for example 18%–21%'),
            'confidence' => $schema->string()->enum(['low', 'medium', 'high'])->required(),
            'summary' => $schema->string()->required()->description('Short paragraph'),
            'insights' => $schema->array()->items($schema->string())->min(2)->max(4)->required(),
            'recommendations' => $schema->array()->items($schema->string())->min(2)->max(4)->required(),
        ];
    }
}
