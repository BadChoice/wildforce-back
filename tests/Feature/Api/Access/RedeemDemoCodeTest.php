<?php

use App\Enums\DemoCodeStatus;
use App\Models\DemoCode;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('it grants permanent demo access for an active demo code', function () {
    $user = User::factory()->create();
    $demoCode = DemoCode::create([
        'code' => 'WILDFORCE-DEMO',
        'status' => DemoCodeStatus::Active,
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/subscription/redeem-code', ['code' => 'wildforce-demo'])
        ->assertOk()
        ->assertJsonPath('demo_code', 'WILDFORCE-DEMO')
        ->assertJsonPath('demo_access_ends_at', null);

    expect($user->fresh()->subscription->demo_code_id)->toBe($demoCode->id)
        ->and($user->fresh()->subscription->isActive())->toBeTrue();
});

test('it rejects expired and previously redeemed demo codes', function () {
    $user = User::factory()->create();
    $expiredCode = DemoCode::create([
        'code' => 'EXPIRED-DEMO',
        'expires_at' => now()->subSecond(),
    ]);

    Sanctum::actingAs($user);

    $this->postJson('/api/subscription/redeem-code', ['code' => $expiredCode->code])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    $activeCode = DemoCode::create(['code' => 'ACTIVE-DEMO']);
    $this->postJson('/api/subscription/redeem-code', ['code' => $activeCode->code])->assertOk();
    $this->postJson('/api/subscription/redeem-code', ['code' => $activeCode->code])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});
