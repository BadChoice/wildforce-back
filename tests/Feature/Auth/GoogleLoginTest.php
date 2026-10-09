<?php

use App\Contracts\GoogleAuthentication;
use App\Models\User;
use App\Services\Google\Auth\GoogleUserAuthenticationResult;
use Mockery\MockInterface;

use function Pest\Laravel\mock;

test('it signs a Google user into the web session', function () {
    $user = User::factory()->create();

    mock(GoogleAuthentication::class, function (MockInterface $mock) use ($user): void {
        $mock->shouldReceive('resolve')
            ->once()
            ->with('google-id-token')
            ->andReturn(new GoogleUserAuthenticationResult($user));
    });

    $response = $this->post(route('google.login'), ['id_token' => 'google-id-token']);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('it redirects a Google user to their intended page', function () {
    $user = User::factory()->create();

    mock(GoogleAuthentication::class, function (MockInterface $mock) use ($user): void {
        $mock->shouldReceive('resolve')->once()->andReturn(new GoogleUserAuthenticationResult($user));
    });

    $response = $this->withSession(['url.intended' => route('subscription.billing')])
        ->post(route('google.login'), ['id_token' => 'google-id-token']);

    $response->assertRedirect(route('subscription.billing'));
});

test('it requires a Google identity token for web login', function () {
    $response = $this->post(route('google.login'));

    $response->assertSessionHasErrors(['id_token']);
    $this->assertGuest();
});

test('the login screen shows Google sign in when a web client is configured', function () {
    config()->set('services.google.web_client_id', 'web-client.apps.googleusercontent.com');

    $response = $this->get(route('login'));

    $response->assertSee('data-google-sign-in', false)
        ->assertSee('web-client.apps.googleusercontent.com');
});
