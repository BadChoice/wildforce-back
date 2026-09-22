<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Concerns\UsesUuidPrimaryKey;
use App\Contracts\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BodyMetricEntry extends Model implements Syncable
{
    use SoftDeletes, SyncsWithUser, UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return ['value' => 'decimal:3', 'recorded_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
