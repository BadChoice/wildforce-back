<?php

namespace App\Models;

use App\Concerns\UsesUuidPrimaryKey;
use App\Enums\DemoCodeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
