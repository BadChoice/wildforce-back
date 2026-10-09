<?php

use App\Ai\Agents\BodyProgress\BodyCompositionPhotoAnalysisAgent;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\ObjectSchema;

test('its structured output schema serializes', function () {
    $schema = (new ObjectSchema((new BodyCompositionPhotoAnalysisAgent('en'))->schema(new JsonSchemaTypeFactory)))->toSchema();

    expect($schema['properties'])->toHaveKeys([
        'estimatedBodyFatRange',
        'confidence',
        'summary',
        'insights',
        'recommendations',
    ]);
});
