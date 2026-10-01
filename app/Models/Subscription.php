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

    /**
     * @return list<string>
     */
    protected static function syncExcludedAttributes(): array
    {
        return [
            'id',
            'user_id',
            'created_at',
            'updated_at',
            'deleted_at',
            'demo_code_id',
            'provider_reference',
        ];
    }
}
