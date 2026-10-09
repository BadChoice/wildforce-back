<?php

use App\Models\User;

test('it blocks and unblocks AI access for a user', function () {
    $user = User::factory()->create();

    $this->artisan('app:ai-block', ['email' => $user->email])->assertSuccessful();
    expect($user->refresh()->isAiBlocked())->toBeTrue();

    $this->artisan('app:ai-block', ['email' => $user->email, '--unblock' => true])->assertSuccessful();
    expect($user->refresh()->isAiBlocked())->toBeFalse();
});

test('it fails for an unknown email', function () {
    $this->artisan('app:ai-block', ['email' => 'missing@example.com'])->assertFailed();
});
