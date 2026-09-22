<?php

namespace App\Models;

use App\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NutritionLogEntry extends Model
{
    use SoftDeletes, UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return ['logged_at' => 'datetime', 'is_favorite' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(NutritionLogMedia::class, 'nutrition_log_media_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(NutritionLogItem::class);
    }
}
