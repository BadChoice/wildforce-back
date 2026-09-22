<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Concerns\UsesUuidPrimaryKey;
use App\Contracts\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserAppSettings extends Model implements Syncable
{
    use SoftDeletes, SyncsWithUser, UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return ['is_health_kit_enabled' => 'boolean', 'is_watch_auto_tracking_enabled' => 'boolean', 'is_screen_on_during_workout_enabled' => 'boolean', 'is_notifications_enabled' => 'boolean', 'is_full_focus_mode_enabled' => 'boolean', 'full_focus_selection' => 'array', 'has_seen_notification_request' => 'boolean', 'has_seen_body_progress_tutorial' => 'boolean', 'has_rated_app' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
