<?php

namespace App\Models;

use App\Concerns\UsesUuidPrimaryKey;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $user_id
 * @property string $provider
 * @property string $provider_user_id
 * @property string|null $provider_email
 * @property CarbonInterface|null $created_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'provider', 'provider_user_id', 'provider_email'])]
class UserIdentity extends Model
{
    use UsesUuidPrimaryKey;

    public const AppleProvider = 'apple';

    public const GoogleProvider = 'google';

    protected $table = 'user_identities';

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
