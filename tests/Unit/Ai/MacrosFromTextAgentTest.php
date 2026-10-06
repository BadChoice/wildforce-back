<?php

use App\Ai\Agents\Nutrition\MacrosFromTextAgent;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\ObjectSchema;

test('its structured output schema serializes', function () {
    $schema = (new ObjectSchema((new MacrosFromTextAgent('en'))->schema(new JsonSchemaTypeFactory)))->toSchema();

    expect($schema['properties']['estimatedMacros']['properties'])->toHaveKeys(['calories', 'protein', 'carbs', 'fat'])
        ->and($schema['properties']['estimatedFoods']['items']['properties']['estimatedMacros']['properties'])->toHaveKeys(['calories', 'protein', 'carbs', 'fat']);
});
