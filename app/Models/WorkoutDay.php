<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Concerns\UsesUuidPrimaryKey;
use App\Contracts\Syncable;
use App\Enums\WorkoutDayType;
use App\Enums\WorkoutFocus;
use App\Enums\WorkoutKind;
use Database\Factories\WorkoutDayFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property WorkoutFocus $focus
 * @property WorkoutDayType|null $day_type
 * @property WorkoutKind $kind
 */
class WorkoutDay extends Model implements Syncable
{
    /** @use HasFactory<WorkoutDayFactory> */
    use HasFactory, SoftDeletes, SyncsWithUser, UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return [
            'kind' => WorkoutKind::class,
            'focus' => WorkoutFocus::class,
            'day_type' => WorkoutDayType::class,
            'did_count_toward_streak' => 'boolean',
            'scheduled_for' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'active_calories_burned' => 'decimal:2',
            'average_heart_rate' => 'decimal:2',
            'maximum_heart_rate' => 'decimal:2',
            'total_volume_kg' => 'decimal:3',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<WorkoutPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(WorkoutPlan::class, 'workout_plan_id');
    }

    /** @return BelongsTo<self, $this> */
    public function sourceWorkoutDay(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_workout_day_id');
    }

    /** @return HasMany<WorkoutBlock, $this> */
    public function blocks(): HasMany
    {
        return $this->hasMany(WorkoutBlock::class);
    }

    /** @return HasMany<PlannedExercise, $this> */
    public function exercises(): HasMany
    {
        return $this->hasMany(PlannedExercise::class);
    }

    public function exercisesCount(): int
    {
        return $this->blocks->sum(fn ($block) => $block->exercises->count());
    }

    /**
     * @param  Builder<WorkoutDay>  $query
     * @return Builder<WorkoutDay>
     */
    public function scopeCustomWorkouts(Builder $query): Builder
    {
        return $query
            ->whereNull('workout_plan_id')
            ->where('kind', WorkoutKind::Workout);
    }

    /**
     * @param  Builder<WorkoutDay>  $query
     * @return Builder<WorkoutDay>
     */
    public function scopeTemplates(Builder $query): Builder
    {
        return $query
            ->whereNull('workout_plan_id')
            ->where('kind', WorkoutKind::Template);
    }

    /**
     * @return list<string>
     */
    protected static function syncExcludedAttributes(): array
    {
        return ['id', 'created_at', 'updated_at', 'deleted_at'];
    }
}
