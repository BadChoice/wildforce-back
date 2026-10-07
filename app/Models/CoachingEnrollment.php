<?php

namespace App\Models;

use App\Concerns\UsesUuidPrimaryKey;
use App\Enums\CoachingEnrollmentStatus;
use Database\Factories\CoachingEnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['client_user_id', 'coach_user_id', 'status', 'starts_at', 'ends_at'])]
class CoachingEnrollment extends Pivot
{
    /** @use HasFactory<CoachingEnrollmentFactory> */
    use HasFactory, UsesUuidPrimaryKey;

    protected $table = 'coaching_enrollments';

    protected function casts(): array
    {
        return [
            'status' => CoachingEnrollmentStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_user_id');
    }
}
