<?php

namespace App\Services\Sync;

use App\Models\NutritionDay;
use App\Models\NutritionMeal;
use App\Models\NutritionPlan;
use App\Models\User;
use App\Models\WorkoutPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NutritionPlanSyncService
{
    public function __construct(private SyncService $syncService) {}

    /**
     * @param  list<array<string, mixed>>  $records
     * @return Collection<int, NutritionPlan>
     */
    public function push(User $user, array $records): Collection
    {
        return DB::transaction(function () use ($user, $records): Collection {
            return collect($records)->map(function (array $record) use ($user): NutritionPlan {
                $this->assertSourceWorkoutPlanBelongsToUser($user, $record['source_workout_plan_id'] ?? null);
                $plan = $this->pushRecord($user, NutritionPlan::class, $record);

                if ($plan->trashed()) {
                    return $this->loadGraph($plan);
                }

                foreach ($record['days'] as $dayRecord) {
                    $day = $this->pushRecord($user, NutritionDay::class, [
                        ...$dayRecord,
                        'nutrition_plan_id' => $plan->getKey(),
                    ]);

                    foreach ($dayRecord['meals'] as $mealRecord) {
                        $this->pushRecord($user, NutritionMeal::class, [
                            ...$mealRecord,
                            'nutrition_day_id' => $day->getKey(),
                        ]);
                    }
                }

                return $this->loadGraph($plan);
            });
        });
    }

    /** @return Collection<int, NutritionPlan> */
    public function pull(User $user, ?Carbon $updatedAfter): Collection
    {
        return $this->graphQuery($user)
            ->when($updatedAfter, function (Builder $query) use ($updatedAfter): void {
                $query->where(function (Builder $query) use ($updatedAfter): void {
                    $query->where('nutrition_plans.updated_at', '>', $updatedAfter)
                        ->orWhereHas('days', fn (Builder $query) => $query->withTrashed()->where('updated_at', '>', $updatedAfter))
                        ->orWhereHas('days.meals', fn (Builder $query) => $query->withTrashed()->where('updated_at', '>', $updatedAfter));
                });
            })
            ->orderBy('nutrition_plans.updated_at')
            ->orderBy('nutrition_plans.id')
            ->get();
    }

    /**
     * @param  class-string<NutritionPlan|NutritionDay|NutritionMeal>  $modelClass
     * @param  array<string, mixed>  $record
     */
    private function pushRecord(User $user, string $modelClass, array $record): NutritionPlan|NutritionDay|NutritionMeal
    {
        $record = Arr::only($record, ['id', 'created_at', 'updated_at', 'deleted_at', ...$modelClass::syncAttributes()]);

        return $this->syncService->push($user, $modelClass, [$record])->sole();
    }

    private function assertSourceWorkoutPlanBelongsToUser(User $user, ?string $workoutPlanId): void
    {
        if ($workoutPlanId !== null && ! WorkoutPlan::query()->withTrashed()->whereKey($workoutPlanId)->whereBelongsTo($user)->exists()) {
            throw ValidationException::withMessages([
                'records' => ['The source workout plan must belong to the authenticated user.'],
            ]);
        }
    }

    private function loadGraph(NutritionPlan $plan): NutritionPlan
    {
        return $this->graphQuery($plan->user)->whereKey($plan)->sole();
    }

    /** @return Builder<NutritionPlan> */
    private function graphQuery(User $user): Builder
    {
        return NutritionPlan::query()
            ->withTrashed()
            ->forUser($user)
            ->with([
                'days' => fn ($query) => $query->withTrashed()->orderBy('date')->orderBy('id'),
                'days.meals' => fn ($query) => $query->withTrashed()->orderBy('order_index')->orderBy('id'),
            ]);
    }
}
