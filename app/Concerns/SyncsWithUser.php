<?php

namespace App\Concerns;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

trait SyncsWithUser
{
    /**
     * @return list<string>
     */
    public static function syncAttributes(): array
    {
        return array_values(array_diff(
            Schema::getColumnListing(static::query()->getModel()->getTable()),
            static::syncExcludedAttributes(),
        ));
    }

    /**
     * @return list<string>
     */
    protected static function syncExcludedAttributes(): array
    {
        return ['id', 'created_at', 'updated_at', 'deleted_at'];
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
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
        $attributes = $this->only(static::syncAttributes());
        $deletedAt = $this->getAttribute('deleted_at');

        foreach ($attributes as $attribute => $value) {
            $cast = $this->getCasts()[$attribute] ?? null;

            if (is_string($cast) && str_starts_with($cast, 'decimal:') && $value !== null) {
                $attributes[$attribute] = (float) $value;
            }
        }

        return [
            ...$attributes,
            'id' => $this->getKey(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $deletedAt instanceof CarbonInterface ? $deletedAt->toISOString() : null,
        ];
    }
}
