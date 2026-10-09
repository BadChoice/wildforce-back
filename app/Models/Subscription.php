<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Concerns\UsesUuidPrimaryKey;
use App\Contracts\Syncable;
use App\Enums\BillingInterval;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionProvider;
use App\Enums\SubscriptionStatus;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property SubscriptionPlan $plan
 * @property SubscriptionProvider $provider
 * @property SubscriptionStatus $status
 * @property bool $auto_renews
 * @property string|null $billing_amount
 * @property string|null $billing_currency
 * @property BillingInterval|null $billing_interval
 * @property int|null $billing_interval_count
 * @property int|null $active_client_limit
 * @property string|null $provider_reference
 * @property Carbon|null $starts_at
 * @property Carbon|null $renews_at
 * @property Carbon|null $cancelled_at
 */
#[Fillable([
    'user_id',
    'demo_code_id',
    'plan',
    'provider',
    'status',
    'auto_renews',
    'billing_amount',
    'billing_currency',
    'billing_interval',
    'billing_interval_count',
    'active_client_limit',
    'provider_reference',
    'starts_at',
    'renews_at',
    'cancelled_at',
])]
class Subscription extends Model implements Syncable
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory, SyncsWithUser;

    use UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return [
            'plan' => SubscriptionPlan::class,
            'provider' => SubscriptionProvider::class,
            'status' => SubscriptionStatus::class,
            'auto_renews' => 'boolean',
            'billing_amount' => 'decimal:3',
            'billing_interval' => BillingInterval::class,
            'billing_interval_count' => 'integer',
            'active_client_limit' => 'integer',
            'starts_at' => 'datetime',
            'renews_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public static function createTrial(?Carbon $startsAt = null): self
    {
        $startsAt ??= now();

        return new self([
            'plan' => SubscriptionPlan::Trial,
            'provider' => SubscriptionProvider::Internal,
            'status' => SubscriptionStatus::Active,
            'starts_at' => $startsAt,
            'renews_at' => $startsAt->copy()->addDays(15),
        ]);
    }

    public static function createCoachTrial(?Carbon $startsAt = null): self
    {
        $startsAt ??= now();

        return new self([
            'plan' => SubscriptionPlan::CoachTrial,
            'provider' => SubscriptionProvider::Internal,
            'status' => SubscriptionStatus::Active,
            'active_client_limit' => SubscriptionPlan::CoachTrial->coachClientLimit()?->limit(),
            'starts_at' => $startsAt,
            'renews_at' => $startsAt->copy()->addDays(15),
        ]);
    }

    public static function createCoachedExternal(?Carbon $startsAt = null): self
    {
        return new self([
            'plan' => SubscriptionPlan::CoachedExternal,
            'provider' => SubscriptionProvider::External,
            'status' => SubscriptionStatus::Active,
            'starts_at' => $startsAt ?? now(),
        ]);
    }

    public static function createDemo(DemoCode $demoCode, ?Carbon $startsAt = null): self
    {
        $startsAt ??= now();

        return new self([
            'demo_code_id' => $demoCode->id,
            'plan' => SubscriptionPlan::Demo,
            'provider' => SubscriptionProvider::Internal,
            'status' => SubscriptionStatus::Active,
            'starts_at' => $startsAt,
            'renews_at' => $demoCode->access_duration_days === null
                ? null
                : $startsAt->copy()->addDays($demoCode->access_duration_days),
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<DemoCode, $this>
     */
    public function demoCode(): BelongsTo
    {
        return $this->belongsTo(DemoCode::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [SubscriptionStatus::Active, SubscriptionStatus::GracePeriod], true)
            && ($this->renews_at === null || $this->renews_at->isFuture());
    }

    public function isActiveCoachSubscription(): bool
    {
        return $this->isActive() && $this->plan->isCoachPlan();
    }

    protected static function booted(): void
    {
        static::creating(function (self $subscription): void {
            if ($subscription->active_client_limit === null) {
                $subscription->active_client_limit = $subscription->plan->coachClientLimit()?->limit();
            }
        });
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActiveCoachSubscriptions(Builder $query): Builder
    {
        return $query
            ->whereIn('plan', SubscriptionPlan::coachPlans())
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::GracePeriod])
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('renews_at')
                    ->orWhere('renews_at', '>', now());
            });
    }

    /**
     * @return list<string>
     */
    protected static function syncExcludedAttributes(): array
    {
        return [
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
            'demo_code_id',
            'provider_reference',
        ];
    }
}
