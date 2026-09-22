<?php

namespace App\Models;

use App\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExerciseResult extends Model
{
    use SoftDeletes, UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'completed_weight' => 'decimal:2', 'per_set_reps' => 'array', 'per_set_weights_kg' => 'array', 'completed_distance_km' => 'decimal:3', 'watch_set_summary' => 'array', 'watch_rep_summaries' => 'array'];
    }

    public function plannedExercise(): BelongsTo
    {
        return $this->belongsTo(PlannedExercise::class);
    }
}
