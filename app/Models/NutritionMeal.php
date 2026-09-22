<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Concerns\UsesUuidPrimaryKey;
use App\Contracts\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NutritionMeal extends Model implements Syncable
{
    use SoftDeletes, SyncsWithUser, UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return ['target_calories' => 'decimal:2', 'target_protein_grams' => 'decimal:2', 'target_carbs_grams' => 'decimal:2', 'target_fat_grams' => 'decimal:2', 'example_foods' => 'array'];
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(NutritionDay::class, 'nutrition_day_id');
    }

    protected static function syncOwnerRelationship(): string
    {
        return 'day.plan.user';
    }
}
