<?php

namespace App\Models;

use App\Concerns\UsesUuidPrimaryKey;
use App\Enums\DemoCodeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property DemoCodeStatus $status
 * @property int|null $access_duration_days
 * @property Carbon|null $expires_at
 */
#[Fillable(['code', 'status', 'access_duration_days', 'expires_at'])]
class DemoCode extends Model
{
    use UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return [
            'status' => DemoCodeStatus::class,
            'access_duration_days' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
