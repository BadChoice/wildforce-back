<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Concerns\UsesUuidPrimaryKey;
use App\Contracts\Syncable;
use Database\Factories\TrainingLocationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingLocation extends Model implements Syncable
{
    /** @use HasFactory<TrainingLocationFactory> */
    use HasFactory, SoftDeletes, SyncsWithUser, UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'is_default' => 'boolean', 'equipment' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
