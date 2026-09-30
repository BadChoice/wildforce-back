<?php

namespace App\Http\Controllers\Api\Sync;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sync\BatchSyncPullRequest;
use App\Services\Sync\BatchSyncPullService;
use Illuminate\Http\JsonResponse;

class BatchSyncPullController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(BatchSyncPullRequest $request, BatchSyncPullService $batchSyncPullService): JsonResponse
    {
        return response()->json($batchSyncPullService->pull(
            $request->user(),
            $request->resources(),
            $request->string('cursor')->toString(),
        ));
    }
}
