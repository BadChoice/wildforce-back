<?php

namespace App\Models;

use App\Concerns\UsesUuidPrimaryKey;
use Database\Factories\ClientInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $email
 * @property string|null $message
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 */
#[Fillable(['coach_user_id', 'email', 'message', 'token_hash', 'expires_at', 'accepted_at', 'revoked_at'])]
class ClientInvitation extends Model
{
    /** @use HasFactory<ClientInvitationFactory> */
    use HasFactory, UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coach_user_id');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }
}
