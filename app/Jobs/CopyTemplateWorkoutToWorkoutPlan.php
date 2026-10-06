<?php

namespace App\Jobs;

use App\Enums\CoachingEnrollmentStatus;
use App\Enums\WorkoutKind;
use App\Models\PlannedExercise;
use App\Models\WorkoutBlock;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CopyTemplateWorkoutToWorkoutPlan implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 3600;

    public function __construct(
        public WorkoutDay $templateWorkout,
        public WorkoutPlan $userWorkoutPlan,
        public ?string $scheduledFor = null,
    ) {}

    public function uniqueId(): string
    {
        return "{$this->templateWorkout->getKey()}:{$this->userWorkoutPlan->getKey()}:{$this->scheduledFor}";
    }

    public function handle(): void
    {
        $templateWorkout = $this->templateWorkout->fresh(['blocks.exercises']);
        $userWorkoutPlan = $this->userWorkoutPlan->fresh();

        if ($templateWorkout === null
            || $userWorkoutPlan === null
            || $templateWorkout->kind !== WorkoutKind::Template
            || ($templateWorkout->user_id !== $userWorkoutPlan->user_id
                && ! $templateWorkout->user->clients(CoachingEnrollmentStatus::Active)->whereKey($userWorkoutPlan->user_id)->exists())) {
            return;
        }

        DB::transaction(function () use ($templateWorkout, $userWorkoutPlan): void {
            $workoutDay = new WorkoutDay;
            $workoutDay->forceFill([
                'user_id' => $userWorkoutPlan->user_id,
                'workout_plan_id' => $userWorkoutPlan->id,
                'source_workout_day_id' => $templateWorkout->id,
                'kind' => WorkoutKind::Workout,
                'title' => $templateWorkout->title,
                'focus' => $templateWorkout->focus,
                'status' => 'planned',
                'did_count_toward_streak' => false,
                'order_index' => ((int) $userWorkoutPlan->workoutDays()->max('order_index')) + 1,
                'intended_weekday' => $templateWorkout->intended_weekday,
                'scheduled_for' => $this->scheduledFor,
                'day_type' => $templateWorkout->day_type,
                'estimated_duration_minutes' => $templateWorkout->estimated_duration_minutes,
                'creation_source' => 'template',
                'notes' => $templateWorkout->notes,
            ]);
            $workoutDay->save();

            foreach ($templateWorkout->blocks as $templateBlock) {
                $workoutBlock = new WorkoutBlock;
                $workoutBlock->forceFill([
                    ...$this->copyAttributes($templateBlock, ['workout_day_id']),
                    'workout_day_id' => $workoutDay->id,
                ]);
                $workoutBlock->save();

                foreach ($templateBlock->exercises as $templateExercise) {
                    $this->copyExercise($templateExercise, $workoutDay, $workoutBlock);
                }
            }
        });
    }

    /**
     * @param  list<string>  $except
     * @return array<string, mixed>
     */
    private function copyAttributes(WorkoutBlock|PlannedExercise $model, array $except = []): array
    {
        return Arr::except($model->getAttributes(), [
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
            ...$except,
        ]);
    }

    private function copyExercise(PlannedExercise $templateExercise, WorkoutDay $workoutDay, WorkoutBlock $workoutBlock): void
    {
        $plannedExercise = new PlannedExercise;
        $plannedExercise->forceFill([
            ...$this->copyAttributes($templateExercise, ['workout_day_id', 'workout_block_id']),
            'workout_day_id' => $workoutDay->id,
            'workout_block_id' => $workoutBlock->id,
        ]);
        $plannedExercise->save();
    }
}
