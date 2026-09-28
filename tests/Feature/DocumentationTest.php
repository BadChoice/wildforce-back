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
        ->assertJsonPath('paths./api/subscription/redeem-code.post.summary', 'Redeem a demo access code')
        ->assertJsonPath('paths./api/account/coaches.get.summary', "Get the authenticated user's active coaches")
        ->assertJsonPath('paths./api/users/avatar.post.summary', "Upload the authenticated user's public avatar")
        ->assertJsonPath('paths./api/body-progress-photo-sessions/{bodyProgressPhotoSession}/photos/{angle}.post.parameters.1.schema.enum', ['profile', 'front', 'torso'])
        ->assertJsonPath('servers.0.url', 'http://wildforce.test');
});
