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
        ->assertJsonPath('servers.0.url', 'http://wildforce.test');
});
