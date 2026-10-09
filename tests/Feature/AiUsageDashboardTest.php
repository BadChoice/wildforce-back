<?php

use App\Models\AiUsage;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('admin.ai-usage'))
        ->assertRedirect(route('login'));
});

test('non-administrators are redirected from the AI usage dashboard', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.ai-usage'))
        ->assertRedirect(route('workout-plans.index'));
});

test('administrators can view current-month AI usage', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['email' => 'alex@example.com']);
    AiUsage::factory()->for($user)->create([
        'input_tokens' => 1_500,
        'output_tokens' => 500,
        'cost_micros' => 12_345,
    ]);
    AiUsage::factory()->for($user)->create([
        'input_tokens' => 2_500,
        'output_tokens' => 500,
        'cost_micros' => 7_655,
    ]);
    AiUsage::factory()->for($user)->create([
        'created_at' => now()->startOfMonth()->subSecond(),
        'cost_micros' => 1_000_000,
    ]);

    $this->actingAs($admin);

    $this->get(route('admin.ai-usage'))
        ->assertSee('AI usage')
        ->assertSee('alex@example.com')
        ->assertSee('5,000')
        ->assertSee('$0.0200');
});

test('administrators see unpriced current-month requests', function () {
    $admin = User::factory()->admin()->create();
    AiUsage::factory()->create(['cost_micros' => null]);

    $this->actingAs($admin);

    $this->get(route('admin.ai-usage'))
        ->assertSee('1 request has no cost because its model has no price.');
});
