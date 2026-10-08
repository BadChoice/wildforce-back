<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Concerns\UsesUuidPrimaryKey;
use App\Contracts\Syncable;
use App\Enums\BudgetSensitivity;
use App\Enums\CookingEffort;
use App\Enums\DietaryStyle;
use Database\Factories\NutritionProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NutritionProfile extends Model implements Syncable
{
    /** @use HasFactory<NutritionProfileFactory> */
    use HasFactory, SoftDeletes, SyncsWithUser, UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return ['dietary_style' => DietaryStyle::class, 'excluded_foods' => 'array', 'allergies_and_intolerances' => 'array', 'cooking_effort' => CookingEffort::class, 'budget_sensitivity' => BudgetSensitivity::class, 'preferred_protein_sources' => 'array', 'dislikes' => 'array', 'wants_meal_suggestions' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
