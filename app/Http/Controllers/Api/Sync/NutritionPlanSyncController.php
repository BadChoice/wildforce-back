<?php

namespace App\Http\Controllers\Api\Sync;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sync\NutritionPlanSyncRequest;
use App\Http\Resources\Api\Sync\NutritionPlanSyncResource;
use App\Services\Sync\NutritionPlanSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class NutritionPlanSyncController extends Controller
{
    public function store(NutritionPlanSyncRequest $request, NutritionPlanSyncService $syncService): AnonymousResourceCollection
    {
        return NutritionPlanSyncResource::collection($syncService->push($request->user(), $request->records()));
    }

    public function index(Request $request, NutritionPlanSyncService $syncService): AnonymousResourceCollection
    {
        $request->validate(['updated_after' => ['nullable', 'date']]);
        $updatedAfter = $request->filled('updated_after') ? Carbon::parse($request->string('updated_after')->toString()) : null;

        return NutritionPlanSyncResource::collection($syncService->pull($request->user(), $updatedAfter));
    }
}
