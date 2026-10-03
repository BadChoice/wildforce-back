<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Concerns\UsesUuidPrimaryKey;
use App\Contracts\Syncable;
use App\Enums\Equipment;
use Database\Factories\TrainingLocationFactory;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrainingLocation extends Model implements Syncable
{
    /** @use HasFactory<TrainingLocationFactory> */
    use HasFactory, SoftDeletes, SyncsWithUser, UsesUuidPrimaryKey;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_default' => 'boolean',
            'equipment' => AsEnumCollection::of(Equipment::class),
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function makeDefault(): TrainingLocation
    {
        return TrainingLocation::make([
            'name' => 'Default',
            'is_default' => true,
            'sort_order' => 0,
            'equipment' => [
                Equipment::Dumbbells,
                Equipment::OlympicBarbell,
                Equipment::FlatBench,
                Equipment::CableMachine,
                Equipment::PullUpBar,
            ],
        ]);
    }
}
