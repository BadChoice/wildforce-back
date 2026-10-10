<?php

namespace App\Http\Controllers\Api\Workouts;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\Workouts\GeneratedWorkoutDayResource;
use App\Models\TrainingLocation;
use App\Models\WorkoutDay;
use App\Services\Workouts\WorkoutDayLocationAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class WorkoutDayLocationAdaptationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, WorkoutDay $workoutDay, WorkoutDayLocationAdapter $adapter): GeneratedWorkoutDayResource
    {
        Gate::authorize('update', $workoutDay);

        $validated = $request->validate([
            'training_location_id' => [
                'required',
                'uuid',
                Rule::exists('training_locations', 'id')
                    ->where('user_id', $workoutDay->user_id)
                    ->whereNull('deleted_at'),
            ],
        ]);

        set_time_limit(120);

        return new GeneratedWorkoutDayResource($adapter->adapt($workoutDay, TrainingLocation::query()->findOrFail($validated['training_location_id'])));
    }
}
