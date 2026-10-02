<?php

namespace App\Services\GooglePlay;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionProvider;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GooglePlaySubscriptionService
{
    public function __construct(private GooglePlayPublisherClient $publisher) {}

    public function synchronize(User $user, string $purchaseToken, string $obfuscatedAccountId): Subscription
    {
        $expectedAccountId = hash('sha256', $user->id);
        if (! hash_equals($expectedAccountId, $obfuscatedAccountId)) {
            throw ValidationException::withMessages(['purchase_token' => ['This Google Play purchase belongs to another account.']]);
        }

        $purchase = $this->publisher->subscriptionPurchase($purchaseToken);
        $actualAccountId = data_get($purchase, 'externalAccountIdentifiers.obfuscatedExternalAccountId');
        if (! is_string($actualAccountId) || ! hash_equals($expectedAccountId, $actualAccountId)) {
            throw ValidationException::withMessages(['purchase_token' => ['This Google Play purchase belongs to another account.']]);
        }

        $productPlans = config('services.google_play.product_plans', []);
        $lineItem = collect($purchase['lineItems'] ?? [])->first(fn ($item) => is_array($item)
            && is_string($item['productId'] ?? null) && isset($productPlans[$item['productId']]));
        if (! is_array($lineItem)) {
            throw ValidationException::withMessages(['purchase_token' => ['This Google Play product is not configured for Wildforce.']]);
        }

        $product = $productPlans[$lineItem['productId']];
        $status = match ($purchase['subscriptionState'] ?? null) {
            'SUBSCRIPTION_STATE_ACTIVE' => SubscriptionStatus::Active,
            'SUBSCRIPTION_STATE_IN_GRACE_PERIOD' => SubscriptionStatus::GracePeriod,
            'SUBSCRIPTION_STATE_PENDING' => SubscriptionStatus::Pending,
            default => SubscriptionStatus::Expired,
        };

        return DB::transaction(function () use ($user, $purchaseToken, $purchase, $lineItem, $product, $status): Subscription {
            $existing = Subscription::query()->where('provider', SubscriptionProvider::GooglePlay)
                ->where('provider_reference', $purchaseToken)->lockForUpdate()->first();
            if ($existing !== null && $existing->user_id !== $user->id) {
                throw ValidationException::withMessages(['purchase_token' => ['This Google Play purchase belongs to another account.']]);
            }
            $attributes = [
                'plan' => SubscriptionPlan::from($product['plan']),
                'provider' => SubscriptionProvider::GooglePlay,
                'status' => $status,
                'auto_renews' => data_get($lineItem, 'autoRenewingPlan.autoRenewEnabled') === true,
                'billing_interval' => BillingInterval::from($product['billing_interval']),
                'billing_interval_count' => $product['billing_interval_count'],
                'provider_reference' => $purchaseToken,
                'starts_at' => Carbon::parse($purchase['startTime'] ?? now()),
                'renews_at' => Carbon::parse($lineItem['expiryTime']),
                'cancelled_at' => $status === SubscriptionStatus::Expired ? now() : null,
            ];
            if ($existing !== null) {
                $existing->fill($attributes)->save();

                return $existing;
            }
            $user->subscription()->lockForUpdate()->first()?->delete();

            return $user->subscription()->save(new Subscription($attributes)) ?? throw new \LogicException('Could not store the Google Play subscription.');
        });
    }
}
