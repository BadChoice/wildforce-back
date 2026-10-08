<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Concerns\UsesUuidPrimaryKey;
use App\Contracts\Syncable;
use App\Enums\CoachingEnrollmentStatus;
use App\Http\Resources\Api\Sync\CoachExerciseContentSyncResource;
use Database\Factories\CoachExerciseContentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

#[Fillable(['coach_user_id', 'exercise', 'image_path', 'youtube_video_id', 'notes'])]
class CoachExerciseContent extends Model implements Syncable
{
    /** @use HasFactory<CoachExerciseContentFactory> */
    use HasFactory, SoftDeletes, SyncsWithUser, UsesUuidPrimaryKey;

    protected $table = 'coach_exercise_content';

    /** @return BelongsTo<User, $this> */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_user_id');
    }

    /**
     * Limit the content to the coaches the user is actively enrolled with.
     */
    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->whereIn(
            'coach_user_id',
            CoachingEnrollment::query()
                ->select('coach_user_id')
                ->where('client_user_id', $user->getKey())
                ->where('status', CoachingEnrollmentStatus::Active->value),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function syncPayload(): array
    {
        return (new CoachExerciseContentSyncResource($this))->resolve();
    }

    public function imageUrl(): ?string
    {
        if ($this->image_path === null) {
            return null;
        }

        return Storage::disk((string) config('filesystems.public_storage_disc'))->url($this->image_path);
    }

    public function youtubeUrl(): ?string
    {
        if ($this->youtube_video_id === null) {
            return null;
        }

        return 'https://www.youtube.com/watch?v='.$this->youtube_video_id;
    }
}
