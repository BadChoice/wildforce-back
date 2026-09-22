<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Concerns\UsesUuidPrimaryKey;
use App\Contracts\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NutritionDay extends Model implements Syncable
{
    use SoftDeletes, SyncsWithUser, UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return ['date' => 'date', 'target_calories' => 'decimal:2', 'target_protein_grams' => 'decimal:2', 'target_carbs_grams' => 'decimal:2', 'target_fat_grams' => 'decimal:2'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(NutritionPlan::class, 'nutrition_plan_id');
    }

    public function meals(): HasMany
    {
        return $this->hasMany(NutritionMeal::class);
    }

    protected static function syncOwnerRelationship(): string
    {
        return 'plan.user';
    }
}
