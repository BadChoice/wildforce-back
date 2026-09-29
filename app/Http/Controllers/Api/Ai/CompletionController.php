<?php

namespace App\Http\Controllers\Api\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Ai\CompletionRequest;
use App\Services\Ai\JsonSchemaTypeConverter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\JsonResponse;
use Laravel\Ai\Responses\StructuredAgentResponse;

use function Laravel\Ai\agent;

class CompletionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(CompletionRequest $request, JsonSchemaTypeConverter $schemaTypeConverter): JsonResponse
    {
        $schema = $request->array('schema');
        $response = agent(
            schema: function (JsonSchema $jsonSchema) use ($schema, $schemaTypeConverter): array {
                return $schemaTypeConverter->objectProperties($jsonSchema, $schema);
            },
        )->prompt(
            $request->string('prompt')->toString(),
            provider: $request->string('provider')->toString(),
            model: $request->string('model')->toString(),
        );

        if (! $response instanceof StructuredAgentResponse) {
            abort(502, 'The AI provider did not return structured JSON.');
        }

        return response()->json($response->toArray());
    }
}
