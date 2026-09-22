<?php

namespace App\Models;

use App\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NutritionLogItem extends Model
{
    use SoftDeletes, UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'amount_grams' => 'decimal:3', 'calories' => 'decimal:2', 'protein_grams' => 'decimal:2', 'carbs_grams' => 'decimal:2', 'fat_grams' => 'decimal:2'];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(NutritionLogEntry::class, 'nutrition_log_entry_id');
    }
}
