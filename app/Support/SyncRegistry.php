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
use App\Models\Subscription;
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
        'subscriptions' => Subscription::class,
    ];

    /**
     * @var list<string>
     */
    private const PUSH_RESOURCES = [
        'users',
        'user-app-settings',
        'training-preferences',
        'training-locations',
        'body-metric-entries',
        'body-progress-photo-sessions',
        'exercise-profiles',
        'nutrition-profiles',
        'nutrition-log-media',
        'nutrition-log-entries',
        'nutrition-log-items',
        'nutrition-plans',
        'nutrition-days',
        'nutrition-meals',
        'workout-plans',
        'workout-days',
        'workout-blocks',
        'planned-exercises',
        'exercise-results',
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
    public function pullResources(): array
    {
        return array_keys(self::MODELS);
    }

    /**
     * @return list<string>
     */
    public function batchPullResources(): array
    {
        return array_values(array_diff(array_keys(self::MODELS), [
            'nutrition-log-media',
        ]));
    }

    /**
     * @return list<string>
     */
    public function pushResources(): array
    {
        return self::PUSH_RESOURCES;
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
