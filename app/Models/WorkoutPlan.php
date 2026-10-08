<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Concerns\UsesUuidPrimaryKey;
use App\Contracts\Syncable;
use App\Enums\MesocyclePhase;
use Database\Factories\WorkoutPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** @property MesocyclePhase|null $phase */
class WorkoutPlan extends Model implements Syncable
{
    /** @use HasFactory<WorkoutPlanFactory> */
    use HasFactory, SoftDeletes, SyncsWithUser, UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return ['starts_on' => 'datetime', 'phase' => MesocyclePhase::class];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<WorkoutDay, $this> */
    public function workoutDays(): HasMany
    {
        return $this->hasMany(WorkoutDay::class);
    }

    /** @return HasMany<NutritionPlan, $this> */
    public function nutritionPlans(): HasMany
    {
        return $this->hasMany(NutritionPlan::class, 'source_workout_plan_id');
    }
}
