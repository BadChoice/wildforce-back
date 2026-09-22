<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Contracts\Syncable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser, Syncable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, Notifiable, PasskeyAuthenticatable, SoftDeletes, SyncsWithUser, TwoFactorAuthenticatable;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'birth_date' => 'date',
            'last_completed_workout_at' => 'datetime',
            'height_cm' => 'integer',
            'weight_kg' => 'decimal:2',
            'password' => 'hashed',
        ];
    }

    public function scopeForUser(Builder $query, self $user): Builder
    {
        return $query->whereKey($user);
    }

    /**
     * @return list<string>
     */
    protected static function syncExcludedAttributes(): array
    {
        return [
            'id',
            'email',
            'email_verified_at',
            'password',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'two_factor_confirmed_at',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    public function appSettings(): HasOne
    {
        return $this->hasOne(UserAppSettings::class);
    }

    public function trainingPreferences(): HasOne
    {
        return $this->hasOne(TrainingPreference::class);
    }

    public function trainingLocations(): HasMany
    {
        return $this->hasMany(TrainingLocation::class);
    }

    public function bodyMetrics(): HasMany
    {
        return $this->hasMany(BodyMetricEntry::class);
    }

    public function bodyProgressPhotoSessions(): HasMany
    {
        return $this->hasMany(BodyProgressPhotoSession::class);
    }

    public function exerciseProfiles(): HasMany
    {
        return $this->hasMany(ExerciseProfile::class);
    }

    public function nutritionProfile(): HasOne
    {
        return $this->hasOne(NutritionProfile::class);
    }

    public function nutritionLogEntries(): HasMany
    {
        return $this->hasMany(NutritionLogEntry::class);
    }

    public function nutritionPlans(): HasMany
    {
        return $this->hasMany(NutritionPlan::class);
    }

    public function workoutPlans(): HasMany
    {
        return $this->hasMany(WorkoutPlan::class);
    }

    public function workoutDays(): HasMany
    {
        return $this->hasMany(WorkoutDay::class);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }
}
