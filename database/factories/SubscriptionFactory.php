<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionProvider;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->withoutSubscription(),
            'plan' => SubscriptionPlan::Premium,
            'provider' => SubscriptionProvider::Stripe,
            'status' => SubscriptionStatus::Active,
            'auto_renews' => true,
            'billing_amount' => 9.99,
            'billing_currency' => 'EUR',
            'billing_interval' => BillingInterval::Month,
            'billing_interval_count' => 1,
            'provider_reference' => fake()->unique()->bothify('sub_????????????'),
            'starts_at' => now()->subMonth(),
            'renews_at' => now()->addMonth(),
        ];
    }
}
