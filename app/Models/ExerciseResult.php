<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Concerns\UsesUuidPrimaryKey;
use App\Contracts\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;

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
            $watchSetSummary = $payload['watch_set_summary'];

            $payload['watch_set_summary'] = [
                ...Arr::except($watchSetSummary, ['cadence_rpm', 'cadence_r_p_m']),
                'rep_count' => $watchSetSummary['rep_count'] ?? 0,
                'cadence_r_p_m' => $watchSetSummary['cadence_rpm'] ?? $watchSetSummary['cadence_r_p_m'] ?? 0,
                'average_rep_duration_seconds' => $watchSetSummary['average_rep_duration_seconds'] ?? 0,
                'average_range_of_motion' => $watchSetSummary['average_range_of_motion'] ?? 0,
                'range_of_motion_drop_percent' => $watchSetSummary['range_of_motion_drop_percent'] ?? 0,
                'cadence_drift_percent' => $watchSetSummary['cadence_drift_percent'] ?? 0,
                'consistency_score' => $watchSetSummary['consistency_score'] ?? 0,
                'average_confidence' => $watchSetSummary['average_confidence'] ?? 0,
                'dominant_velocity_label' => $watchSetSummary['dominant_velocity_label'] ?? 'controlled',
                'alerts' => $watchSetSummary['alerts'] ?? [],
            ];
        }

        return $payload;
    }
}
