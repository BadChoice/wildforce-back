<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Concerns\UsesUuidPrimaryKey;
use App\Contracts\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExerciseProfile extends Model implements Syncable
{
    use SoftDeletes, SyncsWithUser, UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return ['working_weight' => 'decimal:2', 'estimated_one_rep_max' => 'decimal:2', 'typical_distance_km' => 'decimal:3'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
