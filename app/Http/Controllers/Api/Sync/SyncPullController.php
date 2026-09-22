<?php

namespace App\Http\Controllers\Api\Sync;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sync\SyncPullRequest;
use App\Http\Resources\Api\Sync\SyncModelResource;
use App\Services\Sync\SyncService;
use App\Support\SyncRegistry;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class SyncPullController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        SyncPullRequest $request,
        SyncRegistry $syncRegistry,
        SyncService $syncService,
    ): AnonymousResourceCollection {
        $resource = $request->string('resource')->toString();
        $modelClass = $syncRegistry->model($resource);
        $updatedAfter = $request->filled('updated_after')
            ? Carbon::parse($request->string('updated_after')->toString())
            : null;

        $models = $syncService->pull($request->user(), $modelClass, $updatedAfter);

        return SyncModelResource::collection($models)->additional([
            'meta' => [
                'resource' => $resource,
            ],
        ]);
    }
}
