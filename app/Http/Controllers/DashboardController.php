<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        if (Gate::denies('viewDashboard')) {
            return to_route('workout-plans.index');
        }

        return view('dashboard');
    }
}
