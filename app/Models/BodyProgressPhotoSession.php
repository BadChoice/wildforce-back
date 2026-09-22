<?php

namespace App\Models;

use App\Concerns\UsesUuidPrimaryKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BodyProgressPhotoSession extends Model
{
    use SoftDeletes, UsesUuidPrimaryKey;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
