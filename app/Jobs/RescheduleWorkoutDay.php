<?php

namespace App\Jobs;

use App\Enums\WorkoutDayStatus;
use App\Models\WorkoutDay;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RescheduleWorkoutDay implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public WorkoutDay $workoutDay,
        public CarbonImmutable $scheduledFor,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $workoutDay = $this->workoutDay->fresh();

        if ($workoutDay === null || $workoutDay->status !== WorkoutDayStatus::Planned->value) {
            return;
        }

        $workoutDay->forceFill([
            'scheduled_for' => $this->scheduledFor,
            'intended_weekday' => strtolower($this->scheduledFor->format('l')),
        ])->save();
    }
}
