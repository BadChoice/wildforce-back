<?php

use App\Models\User;
use Laravel\Ai\Ai;
use Laravel\Ai\StructuredAnonymousAgent;

function completionRequestPayload(): array
{
    return [
        'prompt' => 'Return the requested greeting.',
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
        'schema' => [
            'type' => 'object',
            'properties' => [
                'greeting' => [
                    'type' => 'string',
                ],
            ],
            'required' => ['greeting'],
        ],
    ];
}

test('it returns the structured completion for an authenticated user', function () {
    Ai::fakeAgent(StructuredAnonymousAgent::class, [['greeting' => 'Hola']])->preventStrayPrompts();
    $user = User::factory()->create();
    $payload = completionRequestPayload();
    $payload['schema'] = json_encode($payload['schema']);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/ai/completions', $payload);

    $response->assertExactJson(['greeting' => 'Hola']);

    Ai::assertAgentWasPrompted(StructuredAnonymousAgent::class, 'Return the requested greeting.');
});

test('it returns 401 when no token is provided', function () {
    $this->postJson('/api/ai/completions', completionRequestPayload())
        ->assertUnauthorized();
});

test('it returns 422 when the response schema is unsupported', function () {
    $user = User::factory()->create();
    $payload = completionRequestPayload();
    $payload['schema']['properties']['greeting'] = ['type' => 'null'];

    $this->actingAs($user, 'sanctum')->postJson('/api/ai/completions', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['schema']);
});
