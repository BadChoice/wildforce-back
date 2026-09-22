<?php

namespace App\Models;

use App\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NutritionPlan extends Model
{
    use SoftDeletes, UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return ['starts_on' => 'datetime', 'daily_calorie_average' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceWorkoutPlan(): BelongsTo
    {
        return $this->belongsTo(WorkoutPlan::class, 'source_workout_plan_id');
    }

    public function days(): HasMany
    {
        return $this->hasMany(NutritionDay::class);
    }
}
