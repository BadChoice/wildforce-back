<?php

namespace App\Models;

use App\Concerns\SyncsWithUser;
use App\Contracts\Syncable;
use App\Enums\CoachingEnrollmentStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
 * @property bool $is_admin
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'language', 'password'])]
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
            'is_admin' => 'boolean',
            'birth_date' => 'date',
            'last_completed_workout_at' => 'datetime',
            'height' => 'integer',
            'weight' => 'decimal:2',
            'password' => 'hashed',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
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

    /** @return HasOne<UserAppSettings, $this> */
    public function appSettings(): HasOne
    {
        return $this->hasOne(UserAppSettings::class);
    }

    /**
     * @return HasOne<UserIdentity, $this>
     */
    public function appleIdentity(): HasOne
    {
        return $this->hasOne(UserIdentity::class)
            ->where('provider', UserIdentity::AppleProvider);
    }

    /**
     * @return HasOne<UserIdentity, $this>
     */
    public function googleIdentity(): HasOne
    {
        return $this->hasOne(UserIdentity::class)
            ->where('provider', UserIdentity::GoogleProvider);
    }

    /**
     * @return HasMany<UserIdentity, $this>
     */
    public function identities(): HasMany
    {
        return $this->hasMany(UserIdentity::class);
    }

    /** @return HasOne<TrainingPreference, $this> */
    public function trainingPreferences(): HasOne
    {
        return $this->hasOne(TrainingPreference::class);
    }

    /** @return HasMany<TrainingLocation, $this> */
    public function trainingLocations(): HasMany
    {
        return $this->hasMany(TrainingLocation::class);
    }

    /** @return HasMany<BodyMetricEntry, $this> */
    public function bodyMetrics(): HasMany
    {
        return $this->hasMany(BodyMetricEntry::class);
    }

    /** @return HasMany<BodyProgressPhotoSession, $this> */
    public function bodyProgressPhotoSessions(): HasMany
    {
        return $this->hasMany(BodyProgressPhotoSession::class);
    }

    /** @return HasMany<ExerciseProfile, $this> */
    public function exerciseProfiles(): HasMany
    {
        return $this->hasMany(ExerciseProfile::class);
    }

    /** @return HasOne<NutritionProfile, $this> */
    public function nutritionProfile(): HasOne
    {
        return $this->hasOne(NutritionProfile::class);
    }

    /** @return HasMany<NutritionLogEntry, $this> */
    public function nutritionLogEntries(): HasMany
    {
        return $this->hasMany(NutritionLogEntry::class);
    }

    /** @return HasMany<NutritionPlan, $this> */
    public function nutritionPlans(): HasMany
    {
        return $this->hasMany(NutritionPlan::class);
    }

    /** @return HasMany<WorkoutPlan, $this> */
    public function workoutPlans(): HasMany
    {
        return $this->hasMany(WorkoutPlan::class);
    }

    /** @return HasMany<WorkoutDay, $this> */
    public function workoutDays(): HasMany
    {
        return $this->hasMany(WorkoutDay::class);
    }

    /**
     * @return HasOne<Subscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function attachSubscription(Subscription $subscription): Subscription
    {
        if ($this->subscription()->exists()) {
            throw new \LogicException('A user can only have one subscription.');
        }

        return $this->subscription()->save($subscription);
    }

    public function replaceSubscription(Subscription $subscription): Subscription
    {
        $this->subscription()->delete();

        $subscription = $this->subscription()->save($subscription);
        $this->unsetRelation('subscription');

        return $subscription;
    }

    public function hasAppAccess(): bool
    {
        return $this->subscription()->first()?->isActive() ?? false;
    }

    public function isAdmin(): bool
    {
        return $this->is_admin ?? false;
    }

    public function canViewUserData(string $userId): bool
    {
        if ($this->isAdmin() || $this->getKey() === $userId) {
            return true;
        }

        return $this->clients(CoachingEnrollmentStatus::Active)
            ->whereKey($userId)
            ->exists();
    }

    /**
     * The user's IANA timezone, falling back to the application timezone when missing or invalid.
     */
    public function preferredTimezone(): string
    {
        if ($this->timezone !== null && in_array($this->timezone, timezone_identifiers_list(), true)) {
            return $this->timezone;
        }

        return (string) config('app.timezone');
    }

    public function isCoach(): bool
    {
        return $this->clients(CoachingEnrollmentStatus::Active)->exists();
    }

    /** @return HasMany<CoachExerciseContent, $this> */
    public function coachExerciseContent(): HasMany
    {
        return $this->hasMany(CoachExerciseContent::class, 'coach_user_id');
    }

    /** @return BelongsToMany<self, $this, CoachingEnrollment, 'enrollment'> */
    public function clients(?CoachingEnrollmentStatus $status = null): BelongsToMany
    {
        $clients = $this->belongsToMany(self::class, 'coaching_enrollments', 'coach_user_id', 'client_user_id')
            ->using(CoachingEnrollment::class)
            ->as('enrollment')
            ->withPivot(['id', 'status', 'starts_at', 'ends_at'])
            ->withTimestamps();

        if ($status !== null) {
            $clients->wherePivot('status', $status->value);
        }

        return $clients;
    }

    /** @return BelongsToMany<self, $this, CoachingEnrollment, 'enrollment'> */
    public function coaches(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'coaching_enrollments', 'client_user_id', 'coach_user_id')
            ->using(CoachingEnrollment::class)
            ->as('enrollment')
            ->withPivot(['id', 'status', 'starts_at', 'ends_at'])
            ->withTimestamps();
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
