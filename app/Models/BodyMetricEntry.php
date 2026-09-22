<?php

namespace App\Models;

use App\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BodyMetricEntry extends Model
{
    use SoftDeletes, UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return ['value' => 'decimal:3', 'recorded_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
