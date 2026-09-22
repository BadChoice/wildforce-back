<?php

namespace App\Http\Controllers\Api\Sync;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sync\SyncPushRequest;
use App\Http\Resources\Api\Sync\SyncModelResource;
use App\Services\Sync\SyncService;
use App\Support\SyncRegistry;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SyncPushController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        SyncPushRequest $request,
        SyncRegistry $syncRegistry,
        SyncService $syncService,
    ): AnonymousResourceCollection {
        $resource = $request->string('resource')->toString();
        $modelClass = $syncRegistry->model($resource);

        $models = $syncService->push(
            $request->user(),
            $modelClass,
            $request->records(),
        );

        return SyncModelResource::collection($models)->additional([
            'meta' => ['resource' => $resource],
        ]);
    }
}
