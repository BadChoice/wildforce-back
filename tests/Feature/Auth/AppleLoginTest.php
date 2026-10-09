<?php

use App\Contracts\AppleAuthentication;
use App\Models\User;
use App\Services\Apple\Auth\AppleUserAuthenticationResult;
use Mockery\MockInterface;

use function Pest\Laravel\mock;

test('it signs an Apple user into the web session', function () {
    $user = User::factory()->create();

    mock(AppleAuthentication::class, function (MockInterface $mock) use ($user): void {
        $mock->shouldReceive('resolveWeb')
            ->once()
            ->with('apple-authorization-code', 'Jane Doe')
            ->andReturn(new AppleUserAuthenticationResult($user));
    });

    $response = $this->post(route('apple.login'), [
        'authorization_code' => 'apple-authorization-code',
        'full_name' => 'Jane Doe',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('it redirects an Apple user to their intended page', function () {
    $user = User::factory()->create();

    mock(AppleAuthentication::class, function (MockInterface $mock) use ($user): void {
        $mock->shouldReceive('resolveWeb')->once()->andReturn(new AppleUserAuthenticationResult($user));
    });

    $response = $this->withSession(['url.intended' => route('subscription.billing')])
        ->post(route('apple.login'), ['authorization_code' => 'apple-authorization-code']);

    $response->assertRedirect(route('subscription.billing'));
});

test('it requires an Apple authorization code for web login', function () {
    $response = $this->post(route('apple.login'));

    $response->assertSessionHasErrors(['authorization_code']);
    $this->assertGuest();
});

test('the login screen shows Apple sign in when a web client is configured', function () {
    config()->set('services.apple.web_client_id', 'io.codepassion.wildforce.web');
    config()->set('services.apple.web_redirect_uri', 'https://wildforce.app/login');

    $response = $this->get(route('login'));

    $response->assertSee('data-apple-sign-in', false)
        ->assertSee('io.codepassion.wildforce.web')
        ->assertSee('https://wildforce.app/login');
});
