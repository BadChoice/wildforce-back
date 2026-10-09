<?php

namespace App\Models;

use App\Concerns\UsesUuidPrimaryKey;
use Database\Factories\AiUsageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string|null $user_id
 * @property string $invocation_id
 * @property string $agent
 * @property string $provider
 * @property string $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int|null $cache_read_input_tokens
 * @property int|null $cache_write_input_tokens
 * @property int|null $reasoning_tokens
 * @property int|null $cost_micros
 * @property Carbon $created_at
 */
#[Fillable([
    'user_id',
    'invocation_id',
    'agent',
    'provider',
    'model',
    'input_tokens',
    'output_tokens',
    'cache_read_input_tokens',
    'cache_write_input_tokens',
    'reasoning_tokens',
    'cost_micros',
])]
class AiUsage extends Model
{
    /** @use HasFactory<AiUsageFactory> */
    use HasFactory, UsesUuidPrimaryKey;

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cache_read_input_tokens' => 'integer',
            'cache_write_input_tokens' => 'integer',
            'reasoning_tokens' => 'integer',
            'cost_micros' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeCurrentMonth(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->startOfMonth());
    }
}
