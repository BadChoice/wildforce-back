<?php

namespace App\Services\AppStore;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionProvider;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AppStoreSubscriptionService
{
    public function __construct(private AppStoreJwsVerifier $jwsVerifier) {}

    public function synchronize(User $user, string $signedTransaction): Subscription
    {
        $transaction = $this->jwsVerifier->verify($signedTransaction);

        return DB::transaction(fn (): Subscription => $this->persist($user, $transaction));
    }

    public function synchronizeNotification(string $signedPayload): ?Subscription
    {
        $notification = $this->jwsVerifier->verify($signedPayload);
        $data = $notification['data'] ?? null;
        $signedTransaction = is_array($data) ? $data['signedTransactionInfo'] ?? null : null;

        if (! is_string($signedTransaction)) {
            return null;
        }

        $transaction = $this->jwsVerifier->verify($signedTransaction);
        $renewalInfo = $this->renewalInfo($data);

        return DB::transaction(function () use ($notification, $transaction, $renewalInfo): ?Subscription {
            $originalTransactionID = $this->requiredString($transaction, 'originalTransactionId');
            $subscription = Subscription::query()
                ->where('provider', SubscriptionProvider::AppStore)
                ->where('provider_reference', $originalTransactionID)
                ->lockForUpdate()
                ->first();
            $user = $subscription?->user;

            if ($user === null) {
                $appAccountToken = $transaction['appAccountToken'] ?? null;
                $user = is_string($appAccountToken) && Str::isUuid($appAccountToken)
                    ? User::query()->find($appAccountToken)
                    : null;
            }

            if ($user === null) {
                return null;
            }

            return $this->persist(
                $user,
                $transaction,
                $this->statusForNotification($notification, $transaction),
                $this->autoRenewalStatus($renewalInfo),
            );
        });
    }

    /**
     * @param  array<string, mixed>  $transaction
     * @param  array<string, mixed>|null  $renewalInfo
     */
    private function persist(
        User $user,
        array $transaction,
        ?SubscriptionStatus $status = null,
        ?bool $autoRenews = null,
    ): Subscription {
        $this->ensureTransactionMatchesApp($transaction);

        $originalTransactionID = $this->requiredString($transaction, 'originalTransactionId');
        $existingSubscription = Subscription::query()
            ->where('provider', SubscriptionProvider::AppStore)
            ->where('provider_reference', $originalTransactionID)
            ->lockForUpdate()
            ->first();

        if ($existingSubscription !== null && $existingSubscription->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'signed_transaction' => ['This App Store subscription belongs to another account.'],
            ]);
        }

        $attributes = [
            'plan' => $this->planForProduct($this->requiredString($transaction, 'productId')),
            'provider' => SubscriptionProvider::AppStore,
            'status' => $status ?? $this->statusForTransaction($transaction),
            'auto_renews' => $autoRenews ?? $this->expiresAt($transaction)?->isFuture(),
            'provider_reference' => $originalTransactionID,
            'starts_at' => $this->purchaseDate($transaction),
            'renews_at' => $this->expiresAt($transaction),
            'cancelled_at' => $autoRenews === false ? now() : null,
        ];

        if ($existingSubscription !== null) {
            $existingSubscription->fill($attributes)->save();

            return $existingSubscription;
        }

        $user->subscription()->lockForUpdate()->first()?->delete();

        return $user->subscription()->save(new Subscription($attributes));
    }

    /** @param array<string, mixed> $transaction */
    private function ensureTransactionMatchesApp(array $transaction): void
    {
        if (($transaction['bundleId'] ?? null) !== config('services.app_store.bundle_id')
            || ($transaction['type'] ?? null) !== 'Auto-Renewable Subscription') {
            throw ValidationException::withMessages([
                'signed_transaction' => ['This is not a valid Wildforce subscription transaction.'],
            ]);
        }
    }

    /** @param array<string, mixed> $transaction */
    private function statusForTransaction(array $transaction): SubscriptionStatus
    {
        if (isset($transaction['revocationDate'])) {
            return SubscriptionStatus::Revoked;
        }

        return $this->expiresAt($transaction)?->isFuture()
            ? SubscriptionStatus::Active
            : SubscriptionStatus::Expired;
    }

    /**
     * @param  array<string, mixed>  $notification
     * @param  array<string, mixed>  $transaction
     */
    private function statusForNotification(array $notification, array $transaction): SubscriptionStatus
    {
        return match ($notification['notificationType'] ?? null) {
            'REVOKE' => SubscriptionStatus::Revoked,
            'EXPIRED', 'GRACE_PERIOD_EXPIRED' => SubscriptionStatus::Expired,
            'DID_FAIL_TO_RENEW' => ($notification['subtype'] ?? null) === 'GRACE_PERIOD'
                ? SubscriptionStatus::GracePeriod
                : $this->statusForTransaction($transaction),
            default => $this->statusForTransaction($transaction),
        };
    }

    /** @param array<string, mixed>|null $data */
    private function renewalInfo(?array $data): ?array
    {
        $signedRenewalInfo = $data['signedRenewalInfo'] ?? null;

        return is_string($signedRenewalInfo) ? $this->jwsVerifier->verify($signedRenewalInfo) : null;
    }

    /** @param array<string, mixed>|null $renewalInfo */
    private function autoRenewalStatus(?array $renewalInfo): ?bool
    {
        if ($renewalInfo === null || ! array_key_exists('autoRenewStatus', $renewalInfo)) {
            return null;
        }

        return (int) $renewalInfo['autoRenewStatus'] === 1;
    }

    private function planForProduct(string $productID): SubscriptionPlan
    {
        $plans = config('services.app_store.product_plans', []);
        $plan = is_array($plans) ? $plans[$productID] ?? null : null;
        $subscriptionPlan = is_string($plan) ? SubscriptionPlan::tryFrom($plan) : null;

        if ($subscriptionPlan === null) {
            throw ValidationException::withMessages([
                'signed_transaction' => ['This App Store product is not configured for Wildforce.'],
            ]);
        }

        return $subscriptionPlan;
    }

    /** @param array<string, mixed> $transaction */
    private function purchaseDate(array $transaction): Carbon
    {
        return $this->dateFromMilliseconds($transaction['purchaseDate'] ?? null, 'purchaseDate');
    }

    /** @param array<string, mixed> $transaction */
    private function expiresAt(array $transaction): ?Carbon
    {
        return array_key_exists('expiresDate', $transaction)
            ? $this->dateFromMilliseconds($transaction['expiresDate'], 'expiresDate')
            : null;
    }

    private function dateFromMilliseconds(mixed $value, string $attribute): Carbon
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                'signed_transaction' => ["The App Store transaction is missing {$attribute}."],
            ]);
        }

        return Carbon::createFromTimestampUTC(((int) $value) / 1000);
    }

    /** @param array<string, mixed> $claims */
    private function requiredString(array $claims, string $attribute): string
    {
        $value = $claims[$attribute] ?? null;
        if (! is_string($value) || $value === '') {
            throw ValidationException::withMessages([
                'signed_transaction' => ["The App Store transaction is missing {$attribute}."],
            ]);
        }

        return $value;
    }
}
