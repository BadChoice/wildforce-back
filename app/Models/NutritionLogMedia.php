<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Concerns\UsesUuidPrimaryKey;
use App\Contracts\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NutritionLogMedia extends Model implements Syncable
{
    use SoftDeletes, SyncsWithUser, UsesUuidPrimaryKey;

    public function entries(): HasMany
    {
        return $this->hasMany(NutritionLogEntry::class);
    }

    protected static function syncOwnerRelationship(): string
    {
        return 'entries.user';
    }
}
