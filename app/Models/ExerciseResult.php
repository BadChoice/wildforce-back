<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Concerns\UsesUuidPrimaryKey;
use App\Contracts\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExerciseResult extends Model implements Syncable
{
    use SoftDeletes, SyncsWithUser, UsesUuidPrimaryKey {
        SyncsWithUser::syncPayload as private baseSyncPayload;
    }

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'completed_weight' => 'decimal:2', 'per_set_reps' => 'array', 'per_set_weights_kg' => 'array', 'completed_distance_km' => 'decimal:3', 'watch_set_summary' => 'array', 'watch_rep_summaries' => 'array'];
    }

    public function plannedExercise(): BelongsTo
    {
        return $this->belongsTo(PlannedExercise::class);
    }

    protected static function syncOwnerRelationship(): string
    {
        return 'plannedExercise.workoutDay.user';
    }

    /**
     * @return array<string, mixed>
     */
    public function syncPayload(): array
    {
        $payload = $this->baseSyncPayload();

        if (is_array($payload['watch_set_summary'] ?? null)) {
            $payload['watch_set_summary'] = [
                'cadence_rpm' => 0,
                ...$payload['watch_set_summary'],
            ];
        }

        return $payload;
    }
}
