<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiUsage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AiUsageController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        if (Gate::denies('viewDashboard')) {
            return to_route('workout-plans.index');
        }

        $totals = AiUsage::query()
            ->currentMonth()
            ->whereNotNull('user_id')
            ->selectRaw('user_id, count(*) as requests, sum(input_tokens + output_tokens) as tokens, sum(cost_micros) as cost_micros')
            ->groupBy('user_id')
            ->orderByDesc('cost_micros')
            ->limit(20)
            ->get();

        $users = User::withTrashed()
            ->with('subscription')
            ->findMany($totals->pluck('user_id'))
            ->keyBy('id');

        return view('admin.ai-usage', [
            'totals' => $totals,
            'users' => $users,
            'totalCostInMicros' => (int) AiUsage::query()->currentMonth()->sum('cost_micros'),
            'unpricedRequests' => AiUsage::query()->currentMonth()->whereNull('cost_micros')->count(),
        ]);
    }
}
