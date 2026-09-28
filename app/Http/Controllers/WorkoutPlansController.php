<?php

namespace App\Http\Controllers;

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
}
