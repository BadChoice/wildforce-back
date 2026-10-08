<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Concerns\UsesUuidPrimaryKey;
use App\Contracts\Syncable;
use App\Enums\MealType;
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
        return ['logged_at' => 'datetime', 'meal_type' => MealType::class, 'is_favorite' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<NutritionLogMedia, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(NutritionLogMedia::class, 'nutrition_log_media_id');
    }

    /** @return BelongsTo<NutritionMeal, $this> */
    public function meal(): BelongsTo
    {
        return $this->belongsTo(NutritionMeal::class, 'nutrition_meal_id');
    }

    /** @return HasMany<NutritionLogItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(NutritionLogItem::class);
    }
}
