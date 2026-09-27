<?php

namespace App\Models;

use App\Concerns\UsesUuidPrimaryKey;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionProvider;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable([
    'user_id',
    'demo_code_id',
    'plan',
    'provider',
    'status',
    'auto_renews',
    'provider_reference',
    'starts_at',
    'renews_at',
    'cancelled_at',
])]
class Subscription extends Model
{
    use UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return [
            'plan' => SubscriptionPlan::class,
            'provider' => SubscriptionProvider::class,
            'status' => SubscriptionStatus::class,
            'auto_renews' => 'boolean',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function demoCode(): BelongsTo
    {
        return $this->belongsTo(DemoCode::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [SubscriptionStatus::Active, SubscriptionStatus::GracePeriod], true)
            && ($this->renews_at === null || $this->renews_at->isFuture());
    }
}
