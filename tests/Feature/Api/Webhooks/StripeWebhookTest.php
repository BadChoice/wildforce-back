<?php

use App\Services\Stripe\StripeSubscriptionService;
use Mockery\MockInterface;
use Stripe\Exception\SignatureVerificationException;

test('it forwards a signed Stripe webhook to the subscription service', function () {
    $this->mock(StripeSubscriptionService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('synchronizeWebhook')
            ->once()
            ->with('', 'signature')
            ->andReturnNull();
    });

    $this->post('/api/webhooks/stripe', [], ['Stripe-Signature' => 'signature'])
        ->assertNoContent();
});

test('it rejects an invalid Stripe webhook signature', function () {
    $this->mock(StripeSubscriptionService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('synchronizeWebhook')
            ->once()
            ->andThrow(new SignatureVerificationException('Invalid signature.'));
    });

    $this->post('/api/webhooks/stripe', [], ['Stripe-Signature' => 'invalid'])
        ->assertBadRequest();
});
