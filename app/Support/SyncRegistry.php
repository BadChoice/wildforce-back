<?php

namespace App\Support;

use App\Contracts\Syncable;
use App\Models\BodyMetricEntry;
use App\Models\BodyProgressPhotoSession;
use App\Models\ExerciseProfile;
use App\Models\ExerciseResult;
use App\Models\NutritionDay;
use App\Models\NutritionLogEntry;
use App\Models\NutritionLogItem;
use App\Models\NutritionLogMedia;
use App\Models\NutritionMeal;
use App\Models\NutritionPlan;
use App\Models\NutritionProfile;
use App\Models\PlannedExercise;
use App\Models\TrainingLocation;
use App\Models\TrainingPreference;
use App\Models\User;
use App\Models\UserAppSettings;
use App\Models\WorkoutBlock;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use InvalidArgumentException;

class SyncRegistry
{
    /**
     * @var array<string, class-string<Syncable>>
     */
    private const MODELS = [
        'users' => User::class,
        'user-app-settings' => UserAppSettings::class,
        'training-preferences' => TrainingPreference::class,
        'training-locations' => TrainingLocation::class,
        'body-metric-entries' => BodyMetricEntry::class,
        'body-progress-photo-sessions' => BodyProgressPhotoSession::class,
        'exercise-profiles' => ExerciseProfile::class,
        'nutrition-profiles' => NutritionProfile::class,
        'nutrition-log-media' => NutritionLogMedia::class,
        'nutrition-log-entries' => NutritionLogEntry::class,
        'nutrition-log-items' => NutritionLogItem::class,
        'nutrition-plans' => NutritionPlan::class,
        'nutrition-days' => NutritionDay::class,
        'nutrition-meals' => NutritionMeal::class,
        'workout-plans' => WorkoutPlan::class,
        'workout-days' => WorkoutDay::class,
        'workout-blocks' => WorkoutBlock::class,
        'planned-exercises' => PlannedExercise::class,
        'exercise-results' => ExerciseResult::class,
    ];

    /**
     * @return list<class-string<Syncable>>
     */
    public function models(): array
    {
        return array_values(self::MODELS);
    }

    /**
     * @return list<string>
     */
    public function resources(): array
    {
        return array_keys(self::MODELS);
    }

    /**
     * @return class-string<Syncable>
     */
    public function model(string $resource): string
    {
        if (isset(self::MODELS[$resource])) {
            return self::MODELS[$resource];
        }

        throw new InvalidArgumentException("Unsupported sync resource [{$resource}].");
    }
}
