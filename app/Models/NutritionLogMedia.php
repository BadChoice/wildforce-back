<?php

namespace App\Models;

use App\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class NutritionLogMedia extends Model
{
    use SoftDeletes, UsesUuidPrimaryKey;

    public function entries(): HasMany
    {
        return $this->hasMany(NutritionLogEntry::class);
    }
}
