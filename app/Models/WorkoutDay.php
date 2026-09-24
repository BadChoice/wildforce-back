<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Concerns\UsesUuidPrimaryKey;
use App\Contracts\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkoutDay extends Model implements Syncable
{
    use SoftDeletes, SyncsWithUser, UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return ['did_count_toward_streak' => 'boolean', 'scheduled_for' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'active_calories_burned' => 'decimal:2', 'average_heart_rate' => 'decimal:2', 'maximum_heart_rate' => 'decimal:2', 'total_volume_kg' => 'decimal:3'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(WorkoutPlan::class, 'workout_plan_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(WorkoutBlock::class);
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(PlannedExercise::class);
    }

    public function directExercises(): HasMany
    {
        return $this->hasMany(PlannedExercise::class)->whereNull('workout_block_id');
    }
}
