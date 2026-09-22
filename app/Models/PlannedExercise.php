<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Concerns\UsesUuidPrimaryKey;
use App\Contracts\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlannedExercise extends Model implements Syncable
{
    use SoftDeletes, SyncsWithUser, UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return ['target_reps' => 'array', 'target_weight_kg' => 'decimal:2', 'target_weights_kg' => 'array', 'target_distance_km' => 'decimal:3', 'set_style_configuration' => 'array'];
    }

    public function workoutDay(): BelongsTo
    {
        return $this->belongsTo(WorkoutDay::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(WorkoutBlock::class, 'workout_block_id');
    }

    public function exerciseResults(): HasMany
    {
        return $this->hasMany(ExerciseResult::class);
    }

    protected static function syncOwnerRelationship(): string
    {
        return 'workoutDay.user';
    }
}
