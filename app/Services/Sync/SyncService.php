<?php

namespace App\Services\Sync;

use App\Contracts\Syncable;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class SyncService
{
    /**
     * @param  class-string<Syncable>  $modelClass
     * @param  list<array<string, mixed>>  $records
     * @return Collection<int, Model&Syncable>
     */
    public function push(User $user, string $modelClass, array $records): Collection
    {
        return DB::transaction(function () use ($user, $modelClass, $records): Collection {
            return collect($records)->map(function (array $record) use ($user, $modelClass): Model {
                $clientUpdatedAt = Carbon::parse($record['updated_at']);
                $model = $this->syncQuery($user, $modelClass)->whereKey($record['id'])->first();
                $isNew = $model === null;

                if ($isNew && $modelClass::query()->withTrashed()->whereKey($record['id'])->exists()) {
                    throw ValidationException::withMessages([
                        'records' => ['Each record must belong to the authenticated user.'],
                    ]);
                }

                if ($model !== null && $model->updated_at?->greaterThan($clientUpdatedAt)) {
                    return $model;
                }

                $model ??= new $modelClass;

                if (! $model instanceof Model) {
                    throw ValidationException::withMessages([
                        'resource' => ['The requested resource is not an Eloquent model.'],
                    ]);
                }

                $model->forceFill([
                    ...array_diff_key($record, array_flip([
                        'id',
                        'created_at',
                        'updated_at',
                        'deleted_at',
                    ])),
                    'id' => $record['id'],
                    ...($isNew ? ['created_at' => Carbon::parse($record['created_at'])] : []),
                    'updated_at' => $clientUpdatedAt,
                    'deleted_at' => $record['deleted_at'] === null ? null : Carbon::parse($record['deleted_at']),
                ]);

                if (Schema::hasColumn($model->getTable(), 'user_id')) {
                    $model->setAttribute('user_id', $user->getKey());
                }

                $model->timestamps = false;

                try {
                    $model->saveQuietly();
                } finally {
                    $model->timestamps = true;
                }

                if (! $this->syncQuery($user, $modelClass)->whereKey($model)->exists()) {
                    throw ValidationException::withMessages([
                        'records' => ['Each record must belong to the authenticated user.'],
                    ]);
                }

                return $model;
            });
        });
    }

    /**
     * @param  class-string<Syncable>  $modelClass
     * @return Collection<int, Model&Syncable>
     */
    public function pull(User $user, string $modelClass, ?Carbon $updatedAfter): Collection
    {
        return $this->syncQuery($user, $modelClass)
            ->when(
                $updatedAfter,
                fn ($query) => $query->where('updated_at', '>', $updatedAfter),
            )
            ->orderBy('updated_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  class-string<Syncable>  $modelClass
     * @return Builder<Syncable>
     */
    private function syncQuery(User $user, string $modelClass): Builder
    {
        return $modelClass::query()->withTrashed()->forUser($user);
    }
}
