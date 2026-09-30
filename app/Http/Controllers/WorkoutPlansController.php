<?php

namespace App\Http\Controllers;

use App\Models\WorkoutPlan;
use Illuminate\View\View;

class WorkoutPlansController extends Controller
{
    /**
     * Display the versioned exercise catalog.
     */
    public function index(): View
    {
        return view('workout-plans.index');
    }

    public function show(WorkoutPlan $workoutPlan): View
    {
        return view('workout-plans.show', ['workoutPlan' => $workoutPlan]);
    }
}
