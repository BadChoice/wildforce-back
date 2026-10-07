<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Concerns\UsesUuidPrimaryKey;
use App\Contracts\Syncable;
use Database\Factories\NutritionLogEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NutritionLogEntry extends Model implements Syncable
{
    /** @use HasFactory<NutritionLogEntryFactory> */
    use HasFactory, SoftDeletes, SyncsWithUser, UsesUuidPrimaryKey;

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

    public function meal(): BelongsTo
    {
        return $this->belongsTo(NutritionMeal::class, 'nutrition_meal_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(NutritionLogItem::class);
    }
}
