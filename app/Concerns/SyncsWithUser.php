<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait SyncsWithUser
{
    /**
     * @return list<string>
     */
    public static function syncAttributes(): array
    {
        return array_values(array_diff(
            Schema::getColumnListing((new static)->getTable()),
            static::syncExcludedAttributes(),
        ));
    }

    /**
     * @return list<string>
     */
    protected static function syncExcludedAttributes(): array
    {
        return ['id', 'user_id', 'created_at', 'updated_at', 'deleted_at'];
    }

    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->whereHas(
            static::syncOwnerRelationship(),
            fn (Builder $query) => $query->whereKey($user),
        );
    }

    protected static function syncOwnerRelationship(): string
    {
        return 'user';
    }

    /**
     * @return array<string, mixed>
     */
    public function syncPayload(): array
    {
        return [
            ...$this->only(static::syncAttributes()),
            'id' => $this->getKey(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
