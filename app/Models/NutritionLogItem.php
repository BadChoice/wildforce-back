<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Concerns\UsesUuidPrimaryKey;
use App\Contracts\Syncable;
use Database\Factories\NutritionLogItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NutritionLogItem extends Model implements Syncable
{
    /** @use HasFactory<NutritionLogItemFactory> */
    use HasFactory, SoftDeletes, SyncsWithUser, UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'amount_grams' => 'decimal:3', 'calories' => 'decimal:2', 'protein_grams' => 'decimal:2', 'carbs_grams' => 'decimal:2', 'fat_grams' => 'decimal:2'];
    }

    /** @return BelongsTo<NutritionLogEntry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(NutritionLogEntry::class, 'nutrition_log_entry_id');
    }

    protected static function syncOwnerRelationship(): string
    {
        return 'entry.user';
    }
}
