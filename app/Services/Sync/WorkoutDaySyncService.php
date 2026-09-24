<?php

namespace App\Services\Sync;

use App\Models\ExerciseResult;
use App\Models\PlannedExercise;
use App\Models\User;
use App\Models\WorkoutBlock;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkoutDaySyncService
{
    public function __construct(private SyncService $syncService) {}

    /**
     * @param  list<array<string, mixed>>  $records
     * @return Collection<int, WorkoutDay>
     */
    public function push(User $user, array $records): Collection
    {
        return DB::transaction(function () use ($user, $records): Collection {
            return collect($records)->map(function (array $record) use ($user): WorkoutDay {
                $this->assertPlanBelongsToUser($user, $record['workout_plan_id'] ?? null);
                $workoutDay = $this->pushRecord($user, WorkoutDay::class, $record);

                if ($workoutDay->trashed()) {
                    return $this->loadGraph($workoutDay);
                }

                foreach ($record['blocks'] as $blockRecord) {
                    $block = $this->pushRecord($user, WorkoutBlock::class, [
                        ...$blockRecord,
                        'workout_day_id' => $workoutDay->getKey(),
                    ]);

                    foreach ($blockRecord['exercises'] as $exerciseRecord) {
                        $this->pushExercise($user, $exerciseRecord, $workoutDay, $block);
                    }
                }

                foreach ($record['exercises'] as $exerciseRecord) {
                    $this->pushExercise($user, $exerciseRecord, $workoutDay);
                }

                return $this->loadGraph($workoutDay);
            });
        });
    }

    /**
     * @return Collection<int, WorkoutDay>
     */
    public function pull(User $user, ?Carbon $updatedAfter): Collection
    {
        return $this->graphQuery($user)
            ->when($updatedAfter, function (Builder $query) use ($updatedAfter): void {
                $query->where(function (Builder $query) use ($updatedAfter): void {
                    $query->where('workout_days.updated_at', '>', $updatedAfter)
                        ->orWhereHas('blocks', fn (Builder $query) => $query->withTrashed()->where('updated_at', '>', $updatedAfter))
                        ->orWhereHas('exercises', fn (Builder $query) => $query->withTrashed()->where('updated_at', '>', $updatedAfter))
                        ->orWhereHas('exercises.exerciseResults', fn (Builder $query) => $query->withTrashed()->where('updated_at', '>', $updatedAfter));
                });
            })
            ->orderBy('workout_days.updated_at')
            ->orderBy('workout_days.id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $record
     */
    private function pushExercise(User $user, array $record, WorkoutDay $workoutDay, ?WorkoutBlock $block = null): void
    {
        $exercise = $this->pushRecord($user, PlannedExercise::class, [
            ...$record,
            'workout_day_id' => $workoutDay->getKey(),
            'workout_block_id' => $block?->getKey(),
        ]);

        foreach ($record['exercise_results'] as $resultRecord) {
            $this->pushRecord($user, ExerciseResult::class, [
                ...$resultRecord,
                'planned_exercise_id' => $exercise->getKey(),
            ]);
        }
    }

    /**
     * @param  class-string<WorkoutDay|WorkoutBlock|PlannedExercise|ExerciseResult>  $modelClass
     * @param  array<string, mixed>  $record
     */
    private function pushRecord(User $user, string $modelClass, array $record): WorkoutDay|WorkoutBlock|PlannedExercise|ExerciseResult
    {
        $record = Arr::only($record, [
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
            ...$modelClass::syncAttributes(),
        ]);

        return $this->syncService->push($user, $modelClass, [$record])->sole();
    }

    private function assertPlanBelongsToUser(User $user, ?string $workoutPlanId): void
    {
        if ($workoutPlanId === null) {
            return;
        }

        if (! WorkoutPlan::query()->withTrashed()->whereKey($workoutPlanId)->whereBelongsTo($user)->exists()) {
            throw ValidationException::withMessages([
                'records' => ['The workout plan must belong to the authenticated user.'],
            ]);
        }
    }

    private function loadGraph(WorkoutDay $workoutDay): WorkoutDay
    {
        return $this->graphQuery($workoutDay->user)
            ->whereKey($workoutDay)
            ->sole();
    }

    /**
     * @return Builder<WorkoutDay>
     */
    private function graphQuery(User $user): Builder
    {
        return WorkoutDay::query()
            ->withTrashed()
            ->forUser($user)
            ->with([
                'blocks' => fn ($query) => $query->withTrashed()->orderBy('order_index')->orderBy('id'),
                'blocks.exercises' => fn ($query) => $query->withTrashed()->orderBy('order_index')->orderBy('id'),
                'blocks.exercises.exerciseResults' => fn ($query) => $query->withTrashed()->orderBy('completed_at')->orderBy('id'),
                'directExercises' => fn ($query) => $query->withTrashed()->orderBy('order_index')->orderBy('id'),
                'directExercises.exerciseResults' => fn ($query) => $query->withTrashed()->orderBy('completed_at')->orderBy('id'),
            ]);
    }
}
