<?php

namespace App\Services\Ai;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use InvalidArgumentException;

class JsonSchemaTypeConverter
{
    /**
     * Convert the JSON Schema format used by the app into Laravel AI's typed schema format.
     *
     * @param  array<string, mixed>  $schema
     * @return array<string, Type>
     */
    public function objectProperties(JsonSchema $jsonSchema, array $schema): array
    {
        if (($schema['type'] ?? null) !== 'object' || ! is_array($schema['properties'] ?? null)) {
            throw new InvalidArgumentException('The response schema must be an object with properties.');
        }

        $requiredProperties = is_array($schema['required'] ?? null) ? $schema['required'] : [];
        $properties = [];

        foreach ($schema['properties'] as $name => $propertySchema) {
            if (! is_string($name) || ! is_array($propertySchema)) {
                throw new InvalidArgumentException('The response schema contains an invalid property.');
            }

            $properties[$name] = $this->type($jsonSchema, $propertySchema)
                ->required(in_array($name, $requiredProperties, true));
        }

        return $properties;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function type(JsonSchema $jsonSchema, array $schema): Type
    {
        $type = $schema['type'] ?? null;
        $isNullable = is_array($type) && in_array('null', $type, true);
        $type = is_array($type) ? array_values(array_filter($type, fn (mixed $value): bool => $value !== 'null')) : $type;

        if (is_array($type)) {
            if (count($type) !== 1 || ! is_string($type[0])) {
                throw new InvalidArgumentException('The response schema contains an unsupported union type.');
            }

            $type = $type[0];
        }

        $schemaType = match ($type) {
            'string' => $jsonSchema->string(),
            'integer' => $jsonSchema->integer(),
            'number' => $jsonSchema->number(),
            'boolean' => $jsonSchema->boolean(),
            'array' => $this->arrayType($jsonSchema, $schema),
            'object' => $jsonSchema->object($this->objectProperties($jsonSchema, $schema)),
            default => throw new InvalidArgumentException('The response schema contains an unsupported type.'),
        };

        if (is_array($schema['enum'] ?? null)) {
            $schemaType->enum($schema['enum']);
        }

        if (is_string($schema['description'] ?? null)) {
            $schemaType->description($schema['description']);
        }

        return $schemaType->nullable($isNullable);
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function arrayType(JsonSchema $jsonSchema, array $schema): Type
    {
        if (! is_array($schema['items'] ?? null)) {
            throw new InvalidArgumentException('Array response schema properties must define items.');
        }

        return $jsonSchema->array()->items($this->type($jsonSchema, $schema['items']));
    }
}
