<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Concerns\UsesUuidPrimaryKey;
use App\Contracts\Syncable;
use Database\Factories\TrainingPreferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingPreference extends Model implements Syncable
{
    /** @use HasFactory<TrainingPreferenceFactory> */
    use HasFactory, SoftDeletes, SyncsWithUser, UsesUuidPrimaryKey {
        SyncsWithUser::syncPayload as private baseSyncPayload;
    }

    protected function casts(): array
    {
        return ['workout_days' => 'array', 'custom_workout_focuses' => 'array', 'movement_restrictions' => 'array', 'skips_warmups' => 'boolean', 'skips_cooldowns' => 'boolean', 'skips_rest_periods' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function syncPayload(): array
    {
        $payload = $this->baseSyncPayload();
        $payload['custom_workout_focuses'] = (object) ($this->custom_workout_focuses ?? []);

        return $payload;
    }
}
