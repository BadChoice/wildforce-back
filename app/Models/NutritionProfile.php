<?php

namespace App\Models;

use App\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NutritionProfile extends Model
{
    use SoftDeletes, UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return ['excluded_foods' => 'array', 'allergies_and_intolerances' => 'array', 'preferred_protein_sources' => 'array', 'dislikes' => 'array', 'wants_meal_suggestions' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
