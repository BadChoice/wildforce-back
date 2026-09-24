<?php

namespace App\Http\Controllers\Api\Sync;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sync\WorkoutDaySyncRequest;
use App\Http\Resources\Api\Sync\WorkoutDaySyncResource;
use App\Services\Sync\WorkoutDaySyncService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class WorkoutDaySyncController extends Controller
{
    public function store(WorkoutDaySyncRequest $request, WorkoutDaySyncService $syncService): AnonymousResourceCollection
    {
        return WorkoutDaySyncResource::collection(
            $syncService->push($request->user(), $request->records()),
        );
    }

    public function index(Request $request, WorkoutDaySyncService $syncService): AnonymousResourceCollection
    {
        $request->validate(['updated_after' => ['nullable', 'date']]);
        $updatedAfter = $request->filled('updated_after')
            ? Carbon::parse($request->string('updated_after')->toString())
            : null;

        return WorkoutDaySyncResource::collection($syncService->pull($request->user(), $updatedAfter));
    }
}
