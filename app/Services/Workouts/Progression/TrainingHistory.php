<?php

namespace App\Services\Workouts\Progression;

use App\Enums\MesocyclePhase;
use App\Models\ExerciseProfile;
use App\Models\ExerciseResult;
use App\Models\User;
use App\Models\WorkoutPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

final class TrainingHistory
{
    public const int RecentPlanLimit = 3;

    /**
     * @var Collection<int, ExerciseProfile>
     */
    private readonly Collection $exerciseProfiles;

    /**
     * @var Collection<int, WorkoutPlan>
     */
    private readonly Collection $recentPlans;

    /**
     * @var Collection<string, Collection<int, ExerciseResult>>
     */
    private readonly Collection $resultsByExercise;

    public function __construct(private readonly User $user)
    {
        $this->exerciseProfiles = $this->user->exerciseProfiles()
            ->select(['id', 'user_id', 'exercise'])
            ->orderBy('exercise')
            ->orderBy('id')
            ->get();

        $this->recentPlans = $this->user->workoutPlans()
            ->select(['id', 'user_id', 'mesocycle_number', 'phase', 'created_at'])
            ->with([
                'workoutDays' => fn (HasMany $query) => $query
                    ->select(['id', 'workout_plan_id', 'status', 'order_index'])
                    ->orderBy('order_index')
                    ->orderBy('id')
                    ->with([
                        'exercises' => fn (HasMany $query) => $query
                            ->select(['id', 'workout_day_id', 'exercise', 'order_index'])
                            ->orderBy('order_index')
                            ->orderBy('id')
                            ->with([
                                'exerciseResults' => fn (HasMany $query) => $query
                                    ->select([
                                        'id',
                                        'planned_exercise_id',
                                        'feedback',
                                        'completed_at',
                                        'completed_sets',
                                        'completed_reps',
                                        'completed_weight',
                                    ])
                                    ->orderBy('completed_at')
                                    ->orderBy('id'),
                            ]),
                    ]),
            ])
            ->latest('created_at')
            ->orderByDesc('id')
            ->limit(self::RecentPlanLimit)
            ->get()
            ->sortBy([
                ['created_at', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $exerciseIds = $this->exerciseProfiles->pluck('exercise')->all();

        $this->resultsByExercise = $exerciseIds === []
            ? collect()
            : ExerciseResult::query()
                ->select([
                    'id',
                    'planned_exercise_id',
                    'completed_at',
                    'completed_reps',
                    'completed_weight',
                ])
                ->with('plannedExercise:id,exercise')
                ->whereHas('plannedExercise', function (Builder $query) use ($exerciseIds): void {
                    $query->whereIn('exercise', $exerciseIds)
                        ->whereHas('workoutDay', function (Builder $query): void {
                            $query->whereHas('plan', function (Builder $query): void {
                                $query->whereBelongsTo($this->user)
                                    ->where(function (Builder $query): void {
                                        $query->whereNull('phase')
                                            ->orWhere('phase', '!=', MesocyclePhase::Deload->value);
                                    });
                            });
                        });
                })
                ->orderBy('completed_at')
                ->orderBy('id')
                ->get()
                ->groupBy(fn (ExerciseResult $result): string => $result->plannedExercise->exercise);
    }

    /**
     * @return Collection<int, ExerciseProfile>
     */
    public function exerciseProfiles(): Collection
    {
        return $this->exerciseProfiles;
    }

    /**
     * @return Collection<int, WorkoutPlan>
     */
    public function recentPlans(): Collection
    {
        return $this->recentPlans;
    }

    /**
     * @return Collection<int, ExerciseResult>
     */
    public function resultsForExercise(string $exercise): Collection
    {
        return $this->resultsByExercise->get($exercise, collect());
    }

    public function currentStreak(): int
    {
        return $this->user->current_streak ?? 0;
    }

    public function nextMesocycleNumber(): int
    {
        $highestMesocycleNumber = $this->user->workoutPlans()->max('mesocycle_number');

        return $highestMesocycleNumber === null
            ? $this->user->workoutPlans()->count() + 1
            : $highestMesocycleNumber + 1;
    }
}
