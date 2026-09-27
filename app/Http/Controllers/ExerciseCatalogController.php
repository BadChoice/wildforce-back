<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class ExerciseCatalogController extends Controller
{
    /**
     * Display the versioned exercise catalog.
     */
    public function index(): View
    {
        return view('exercises.index');
    }
}
