<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Concerns\UsesUuidPrimaryKey;
use App\Contracts\Syncable;
use App\Enums\NutritionPlanStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\NutritionPlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NutritionPlan extends Model implements Syncable
{
    /** @use HasFactory<NutritionPlanFactory> */
    use HasFactory, SoftDeletes, SyncsWithUser, UsesUuidPrimaryKey;

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

    /**
     * The plan's first calendar day.
     */
    public function startDate(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->starts_on->toDateString());
    }

    /**
     * The plan's last calendar day; generated plans always cover seven days.
     */
    public function endDate(): CarbonImmutable
    {
        return $this->startDate()->addDays(6);
    }

    public function statusOn(CarbonInterface $date): NutritionPlanStatus
    {
        $day = $date->toDateString();

        return match (true) {
            $day < $this->startDate()->toDateString() => NutritionPlanStatus::Upcoming,
            $day > $this->endDate()->toDateString() => NutritionPlanStatus::Past,
            default => NutritionPlanStatus::Active,
        };
    }
}
