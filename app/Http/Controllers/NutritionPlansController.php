<?php

namespace App\Http\Controllers;

use App\Models\NutritionPlan;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class NutritionPlansController extends Controller
{
    public function index(): View
    {
        return view('nutrition-plans.index');
    }

    public function show(NutritionPlan $nutritionPlan): View
    {
        Gate::authorize('view', $nutritionPlan);

        return view('nutrition-plans.show', ['nutritionPlan' => $nutritionPlan]);
    }
}
