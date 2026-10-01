<?php

namespace App\Services\Sync;

use App\Contracts\Syncable;
use App\Models\User;
use App\Support\SyncRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

class BatchSyncPullService
{
    private const CURSOR_TTL_MINUTES = 20;

    private const PAGE_SIZE = 100;

    /**
     * @param  list<array{resource: string, updated_after: string|null}>  $requestedResources
     * @return array{data: list<array{resource: string, record: array<string, mixed>}>, meta: array{as_of: string, next_cursor: string|null}}
     */
    public function pull(User $user, array $requestedResources, string $cursor): array
    {
        $state = $cursor === ''
            ? $this->initialState($user, $requestedResources)
            : $this->cursorState($user, $cursor);
        $asOf = Carbon::parse($state['as_of']);
        $records = [];
        $hasUnfetchedRecords = false;

        foreach ($state['resources'] as $index => $resourceState) {
            /** @var class-string<Syncable> $modelClass */
            $modelClass = $this->syncRegistry->model($resourceState['resource']);
            $candidates = $this->syncService->queryForUser($user, $modelClass)
                ->where('updated_at', '<=', $asOf)
                ->when(
                    $resourceState['updated_after'],
                    fn ($query, string $updatedAfter) => $query->where('updated_at', '>', Carbon::parse($updatedAfter)),
                )
                ->when(
                    $resourceState['last_updated_at'],
                    function ($query, string $lastUpdatedAt) use ($resourceState): void {
                        $query->where(function ($query) use ($lastUpdatedAt, $resourceState): void {
                            $query->where('updated_at', '>', Carbon::parse($lastUpdatedAt))
                                ->orWhere(function ($query) use ($lastUpdatedAt, $resourceState): void {
                                    $query->where('updated_at', Carbon::parse($lastUpdatedAt))
                                        ->where('id', '>', $resourceState['last_id']);
                                });
                        });
                    },
                )
                ->orderBy('updated_at')
                ->orderBy('id')
                ->limit(self::PAGE_SIZE + 1)
                ->get();

            $hasUnfetchedRecords = $hasUnfetchedRecords || $candidates->count() > self::PAGE_SIZE;

            foreach ($candidates->take(self::PAGE_SIZE) as $model) {
                $records[] = [
                    'resource_index' => $index,
                    'resource' => $resourceState['resource'],
                    'model' => $model,
                ];
            }
        }

        usort($records, function (array $left, array $right): int {
            /** @var Model $leftModel */
            $leftModel = $left['model'];
            /** @var Model $rightModel */
            $rightModel = $right['model'];

            return [
                $leftModel->getAttribute('updated_at')->toISOString(),
                $left['resource'],
                $leftModel->getKey(),
            ] <=> [
                $rightModel->getAttribute('updated_at')->toISOString(),
                $right['resource'],
                $rightModel->getKey(),
            ];
        });

        $emittedRecords = array_slice($records, 0, self::PAGE_SIZE);
        $hasMoreRecords = $hasUnfetchedRecords || count($records) > count($emittedRecords);

        foreach ($emittedRecords as $record) {
            /** @var Model&Syncable $model */
            $model = $record['model'];
            $state['resources'][$record['resource_index']]['last_updated_at'] = Carbon::parse(
                $model->getAttribute('updated_at'),
            )->toISOString();
            $state['resources'][$record['resource_index']]['last_id'] = (string) $model->getKey();
        }

        return [
            'data' => array_map(function (array $record): array {
                /** @var Syncable $model */
                $model = $record['model'];

                return [
                    'resource' => $record['resource'],
                    'record' => $model->syncPayload(),
                ];
            }, $emittedRecords),
            'meta' => [
                'as_of' => $asOf->toISOString(),
                'next_cursor' => $hasMoreRecords ? $this->encodeCursor($state) : null,
            ],
        ];
    }

    public function __construct(
        private readonly SyncService $syncService,
        private readonly SyncRegistry $syncRegistry,
    ) {}

    /**
     * @param  list<array{resource: string, updated_after: string|null}>  $requestedResources
     * @return array{version: int, user_id: string, expires_at: string, as_of: string, resources: list<array{resource: string, updated_after: string|null, last_updated_at: string|null, last_id: string|null}>}
     */
    private function initialState(User $user, array $requestedResources): array
    {
        $now = now();

        return [
            'version' => 1,
            'user_id' => $user->getKey(),
            'expires_at' => $now->copy()->addMinutes(self::CURSOR_TTL_MINUTES)->toISOString(),
            'as_of' => $now->toISOString(),
            'resources' => array_map(fn (array $resource): array => [
                'resource' => $resource['resource'],
                'updated_after' => $resource['updated_after'] ?? null,
                'last_updated_at' => null,
                'last_id' => null,
            ], $requestedResources),
        ];
    }

    /**
     * @return array{version: int, user_id: string, expires_at: string, as_of: string, resources: list<array{resource: string, updated_after: string|null, last_updated_at: string|null, last_id: string|null}>}
     */
    private function cursorState(User $user, string $cursor): array
    {
        try {
            /** @var mixed $state */
            $state = json_decode(Crypt::decryptString($cursor), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['cursor' => ['The cursor is invalid.']]);
        }

        if (! is_array($state)
            || ($state['version'] ?? null) !== 1
            || ($state['user_id'] ?? null) !== $user->getKey()
            || ! is_string($state['expires_at'] ?? null)
            || ! is_string($state['as_of'] ?? null)
            || ! is_array($state['resources'] ?? null)
            || Carbon::parse($state['expires_at'])->isPast()) {
            throw ValidationException::withMessages(['cursor' => ['The cursor is invalid or has expired.']]);
        }

        foreach ($state['resources'] as $resourceState) {
            if (! is_array($resourceState)
                || ! is_string($resourceState['resource'] ?? null)
                || ! in_array($resourceState['resource'], $this->syncRegistry->batchPullResources(), true)
                || ! array_key_exists('updated_after', $resourceState)
                || ! array_key_exists('last_updated_at', $resourceState)
                || ! array_key_exists('last_id', $resourceState)) {
                throw ValidationException::withMessages(['cursor' => ['The cursor is invalid.']]);
            }
        }

        return $state;
    }

    /**
     * @param  array{version: int, user_id: string, expires_at: string, as_of: string, resources: list<array{resource: string, updated_after: string|null, last_updated_at: string|null, last_id: string|null}>}  $state
     */
    private function encodeCursor(array $state): string
    {
        return Crypt::encryptString(json_encode($state, JSON_THROW_ON_ERROR));
    }
}
