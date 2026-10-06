<?php

test('it renders the API documentation and its OpenAPI document locally', function () {
    app()->detectEnvironment(fn () => 'local');

    $documentation = $this->get('http://wildforce.test/docs');
    $openApiDocument = $this->get('/docs/v1');

    $documentation->assertOk()
        ->assertSee('swagger-ui');

    $openApiDocument->assertOk()
        ->assertJsonPath('openapi', '3.1.0')
        ->assertJsonPath('info.title', 'Wildforce API')
        ->assertJsonPath('paths./api/auth/google.post.summary', 'Sign in with Google and issue a bearer token')
        ->assertJsonPath('paths./api/account/identities/google.post.summary', 'Link Google to the authenticated user')
        ->assertJsonPath('paths./api/subscription/redeem-code.post.summary', 'Redeem a demo access code')
        ->assertJsonPath('paths./api/account/coaches.get.summary', "Get the authenticated user's active coaches")
        ->assertJsonPath('paths./api/workout-plans/generate.post.summary', 'Generate and save a one-week workout plan')
        ->assertJsonPath('paths./api/nutrition-plans/generate.post.summary', 'Generate and save a seven-day nutrition plan')
        ->assertJsonPath('paths./api/nutrition-macros/generate.post.summary', 'Estimate meal macros from a text description')
        ->assertJsonPath('paths./api/workout-plans/generate.post.parameters.0.$ref', '#/components/parameters/IdempotencyKey')
        ->assertJsonPath('paths./api/users/avatar.post.summary', "Upload the authenticated user's public avatar")
        ->assertJsonPath('paths./api/body-progress-photo-sessions/{bodyProgressPhotoSession}/photos/{angle}.post.parameters.1.schema.enum', ['profile', 'front', 'torso'])
        ->assertJsonPath('paths./api/nutrition-log-entries/{nutritionLogEntry}/image.post.summary', 'Upload a private nutrition log entry image')
        ->assertJsonPath('paths./api/subscription/access.get.summary', "Get the authenticated user's app access")
        ->assertJsonPath('paths./api/ai/completions.post.summary', 'Generate a structured AI completion')
        ->assertJsonPath('servers.0.url', 'http://wildforce.test');
});
