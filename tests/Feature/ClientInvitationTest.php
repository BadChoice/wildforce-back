<?php

use App\Enums\CoachingEnrollmentStatus;
use App\Enums\SubscriptionPlan;
use App\Mail\ClientInvitationMail;
use App\Models\ClientInvitation;
use App\Models\CoachingEnrollment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

function coachWithClientLimit(int $limit = 5): User
{
    $coach = User::factory()->create();
    $coach->subscription()->delete();

    Subscription::factory()->for($coach)->create([
        'plan' => SubscriptionPlan::CoachBasic,
        'active_client_limit' => $limit,
    ]);

    return $coach;
}

test('a coach can invite a client in their language and the invitation reserves a client place', function () {
    Mail::fake();
    $coach = coachWithClientLimit();
    $coach->update(['language' => 'ca']);
    $this->actingAs($coach);

    Livewire::test('dashboard.clients')
        ->assertSee('Invite client')
        ->assertSee('0/5')
        ->call('openInviteClientModal')
        ->set('invitationEmail', 'client@example.com')
        ->set('invitationMessage', 'I would love to work with you.')
        ->call('sendClientInvitation')
        ->assertSet('showInviteClientModal', false)
        ->assertSee('0/5')
        ->assertSee('Invitation sent to client@example.com.');

    $invitation = ClientInvitation::query()->sole();

    expect($invitation->coach_user_id)->toBe($coach->id)
        ->and($invitation->email)->toBe('client@example.com')
        ->and($invitation->message)->toBe('I would love to work with you.')
        ->and($invitation->expires_at->isFuture())->toBeTrue();

    Mail::assertQueued(ClientInvitationMail::class, function (ClientInvitationMail $mail): bool {
        return $mail->hasTo('client@example.com')
            && $mail->locale === 'ca';
    });
});

test('a coach cannot invite more clients than their subscription allows', function () {
    Mail::fake();
    $coach = coachWithClientLimit();

    User::factory()->count(5)->create()->each(function (User $client) use ($coach): void {
        CoachingEnrollment::create([
            'coach_user_id' => $coach->id,
            'client_user_id' => $client->id,
            'status' => CoachingEnrollmentStatus::Active,
            'starts_at' => now(),
        ]);
    });

    $this->actingAs($coach);

    Livewire::test('dashboard.clients')
        ->call('openInviteClientModal')
        ->set('invitationEmail', 'new-client@example.com')
        ->call('sendClientInvitation')
        ->assertHasErrors(['invitationEmail']);

    $this->assertDatabaseCount('client_invitations', 0);
    Mail::assertNothingQueued();
});

test('an invitation sends a guest to a prefilled registration flow', function () {
    $coach = coachWithClientLimit();
    $token = 'valid-client-invitation-token';
    ClientInvitation::factory()->for($coach, 'coach')->create([
        'email' => 'client@example.com',
        'token_hash' => hash('sha256', $token),
    ]);

    $response = $this->get(route('client-invitations.show', ['token' => $token]));

    $response->assertRedirect(route('register', ['invitation' => $token]));
});

test('an expired invitation cannot be opened', function () {
    $coach = coachWithClientLimit();
    $token = 'expired-client-invitation-token';
    ClientInvitation::factory()->for($coach, 'coach')->create([
        'token_hash' => hash('sha256', $token),
        'expires_at' => now()->subMinute(),
    ]);

    $response = $this->get(route('client-invitations.show', ['token' => $token]));

    $response->assertNotFound();
});

test('registration from an invitation creates an external coached subscription and enrollment', function () {
    $coach = coachWithClientLimit();
    $token = 'registration-client-invitation-token';
    $invitation = ClientInvitation::factory()->for($coach, 'coach')->create([
        'email' => 'client@example.com',
        'token_hash' => hash('sha256', $token),
    ]);

    $response = $this->post(route('register.store'), [
        'name' => 'Client Example',
        'email' => 'client@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'invitation' => $token,
    ]);

    $client = User::query()->where('email', 'client@example.com')->sole();

    $response->assertSessionHasNoErrors();
    expect($client->subscription->plan)->toBe(SubscriptionPlan::CoachedExternal);
    $this->assertDatabaseHas('coaching_enrollments', [
        'coach_user_id' => $coach->id,
        'client_user_id' => $client->id,
        'status' => CoachingEnrollmentStatus::Active->value,
    ]);
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('an existing user can accept an invitation sent to their email address', function () {
    $coach = coachWithClientLimit();
    $client = User::factory()->create(['email' => 'client@example.com']);
    $token = 'existing-client-invitation-token';
    $invitation = ClientInvitation::factory()->for($coach, 'coach')->create([
        'email' => $client->email,
        'token_hash' => hash('sha256', $token),
    ]);

    $response = $this->actingAs($client)->get(route('client-invitations.show', ['token' => $token]));

    $response->assertRedirect(route('dashboard'));
    $this->assertDatabaseHas('coaching_enrollments', [
        'coach_user_id' => $coach->id,
        'client_user_id' => $client->id,
        'status' => CoachingEnrollmentStatus::Active->value,
    ]);
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

test('an invitation cannot be accepted by a different email address', function () {
    $coach = coachWithClientLimit();
    $token = 'private-client-invitation-token';
    ClientInvitation::factory()->for($coach, 'coach')->create([
        'email' => 'client@example.com',
        'token_hash' => hash('sha256', $token),
    ]);

    $response = $this->actingAs(User::factory()->create(['email' => 'different@example.com']))
        ->get(route('client-invitations.show', ['token' => $token]));

    $response->assertForbidden();
    $this->assertDatabaseCount('coaching_enrollments', 0);
});

test('the invitation email escapes the coach message', function () {
    $invitation = ClientInvitation::factory()->for(coachWithClientLimit(), 'coach')->create([
        'message' => '<script>alert("unsafe")</script>',
    ]);

    $mail = new ClientInvitationMail($invitation, 'email-client-invitation-token');

    expect($mail->render())
        ->toContain('&lt;script&gt;')
        ->not->toContain('<script>alert("unsafe")</script>');
});

test('the invitation email is translated in the coach language', function () {
    $previousLocale = app()->currentLocale();
    app()->setLocale('ca');

    try {
        $invitation = ClientInvitation::factory()->for(coachWithClientLimit(), 'coach')->create();
        $mail = new ClientInvitationMail($invitation, 'localized-client-invitation-token');

        expect($mail->envelope()->subject)->toBe("Has estat convidat a Wildforce per {$invitation->coach->name}")
            ->and($mail->render())->toContain("Has estat convidat a Wildforce per {$invitation->coach->name}")
            ->and($mail->render())->toContain('Crea el teu compte');
    } finally {
        app()->setLocale($previousLocale);
    }
});
