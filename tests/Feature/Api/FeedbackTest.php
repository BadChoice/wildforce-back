<?php

use App\Mail\FeedbackSubmitted;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('it emails feedback from the authenticated user', function () {
    Mail::fake();
    config(['services.feedback.recipient' => 'feedback@example.com']);
    $user = User::factory()->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/feedback', [
        'category' => 'Bug Report',
        'message' => 'The save button does not respond.',
    ]);

    $response->assertNoContent();

    Mail::assertSent(FeedbackSubmitted::class, function (FeedbackSubmitted $mail) use ($user): bool {
        return $mail->hasTo('feedback@example.com')
            && $mail->user->is($user)
            && $mail->category === 'Bug Report'
            && $mail->feedbackMessage === 'The save button does not respond.';
    });
});

test('it requires authentication to submit feedback', function () {
    $this->postJson('/api/feedback', [
        'category' => 'Bug Report',
        'message' => 'The save button does not respond.',
    ])->assertUnauthorized();
});

test('it rejects an empty feedback message', function () {
    Mail::fake();
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')->postJson('/api/feedback', [
        'category' => 'Bug Report',
        'message' => '   ',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['message']);

    Mail::assertNothingSent();
});

test('it escapes feedback content in the email', function () {
    $user = User::factory()->make([
        'name' => 'Jane <script>alert(1)</script>',
    ]);
    $mail = new FeedbackSubmitted(
        user: $user,
        category: 'Bug <script>alert(1)</script>',
        feedbackMessage: '<script>alert(1)</script>',
    );

    expect($mail->render())
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->not->toContain('<script>alert(1)</script>');
});
