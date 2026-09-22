<?php

namespace App\Models;

use App\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NutritionMeal extends Model
{
    use SoftDeletes, UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return ['target_calories' => 'decimal:2', 'target_protein_grams' => 'decimal:2', 'target_carbs_grams' => 'decimal:2', 'target_fat_grams' => 'decimal:2', 'example_foods' => 'array'];
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(NutritionDay::class, 'nutrition_day_id');
    }
}
