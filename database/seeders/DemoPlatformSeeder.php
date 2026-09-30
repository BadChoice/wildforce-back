<?php

namespace Database\Seeders;

use App\Enums\BillingInterval;
use App\Enums\CoachingEnrollmentStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionProvider;
use App\Models\BodyMetricEntry;
use App\Models\BodyProgressPhotoSession;
use App\Models\CoachingEnrollment;
use App\Models\ExerciseProfile;
use App\Models\ExerciseResult;
use App\Models\NutritionLogEntry;
use App\Models\NutritionLogMedia;
use App\Models\NutritionPlan;
use App\Models\NutritionProfile;
use App\Models\Subscription;
use App\Models\TrainingLocation;
use App\Models\TrainingPreference;
use App\Models\User;
use App\Models\UserAppSettings;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

class DemoPlatformSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $coach = $this->createUser('Sofia Ferrer', 'coach@wildforce.test', [
            'height_cm' => 168,
            'weight_kg' => 61.2,
            'gender' => 'female',
            'current_streak' => 12,
            'longest_streak' => 45,
            'xp' => 2100,
            'xp_level' => 12,
        ], SubscriptionPlan::CoachProMonthly);

        $activeClient = $this->createUser('Marta Soler', 'marta@wildforce.test', [
            'height_cm' => 172,
            'weight_kg' => 68.4,
            'gender' => 'female',
            'current_streak' => 4,
            'longest_streak' => 18,
            'last_completed_workout_at' => now()->subDay(),
            'xp' => 840,
            'xp_level' => 6,
        ], SubscriptionPlan::CoachedExternal);

        $newClient = $this->createUser('Pau Riera', 'pau@wildforce.test', [
            'height_cm' => 180,
            'weight_kg' => 82.7,
            'gender' => 'male',
            'current_streak' => 0,
            'longest_streak' => 2,
            'xp' => 125,
            'xp_level' => 2,
        ], SubscriptionPlan::CoachedExternal);

        $independentUser = $this->createUser('Laia Costa', 'laia@wildforce.test', [
            'height_cm' => 165,
            'weight_kg' => 59.8,
            'gender' => 'female',
            'current_streak' => 7,
            'longest_streak' => 22,
            'last_completed_workout_at' => now()->subDays(2),
            'xp' => 1120,
            'xp_level' => 8,
        ], SubscriptionPlan::Premium);

        CoachingEnrollment::factory()->create([
            'client_user_id' => $activeClient->id,
            'coach_user_id' => $coach->id,
            'status' => CoachingEnrollmentStatus::Active,
            'starts_at' => now()->subMonths(4),
        ]);

        CoachingEnrollment::factory()->create([
            'client_user_id' => $newClient->id,
            'coach_user_id' => $coach->id,
            'status' => CoachingEnrollmentStatus::Active,
            'starts_at' => now()->subWeeks(2),
        ]);

        $this->seedProfileData($coach);
        $this->seedProfileData($activeClient);
        $this->seedProfileData($newClient);
        $this->seedProfileData($independentUser);

        $this->seedWorkoutAndNutritionData($activeClient, includeCompletedHistory: true);
        $this->seedWorkoutAndNutritionData($newClient, includeCompletedHistory: false);
        $this->seedWorkoutAndNutritionData($independentUser, includeCompletedHistory: true);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createUser(string $name, string $email, array $attributes, SubscriptionPlan $subscriptionPlan): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
            'birth_date' => fake()->dateTimeBetween('-45 years', '-25 years')->format('Y-m-d'),
            'language' => 'ca',
            ...$attributes,
        ]);

        $user->replaceSubscription(Subscription::factory()->make([
            'user_id' => null,
            'plan' => $subscriptionPlan,
            'provider' => $subscriptionPlan === SubscriptionPlan::CoachedExternal
                ? SubscriptionProvider::External
                : SubscriptionProvider::Stripe,
            'billing_interval' => BillingInterval::Month,
        ]));

        return $user;
    }

    private function seedProfileData(User $user): void
    {
        UserAppSettings::factory()->for($user)->create();
        TrainingPreference::factory()->for($user)->create();
        TrainingLocation::factory()->for($user)->create();
        NutritionProfile::factory()->for($user)->create();

        ExerciseProfile::factory()
            ->count(4)
            ->sequence(
                ['exercise' => 'benchPress'],
                ['exercise' => 'barbellBackSquat'],
                ['exercise' => 'latPulldown'],
                ['exercise' => 'romanianDeadlift'],
            )
            ->for($user)
            ->create();

        BodyMetricEntry::factory()
            ->count(8)
            ->sequence(fn (Sequence $sequence) => [
                'value' => (float) $user->weight_kg - (($sequence->count - $sequence->index - 1) * 0.2),
                'recorded_at' => now()->subWeeks(7 - $sequence->index)->startOfDay(),
            ])
            ->for($user)
            ->create();

        BodyProgressPhotoSession::factory()
            ->count(2)
            ->sequence(
                ['created_at' => now()->subMonth(), 'updated_at' => now()->subMonth()],
                ['created_at' => now()->subDay(), 'updated_at' => now()->subDay()],
            )
            ->for($user)
            ->create();
    }

    private function seedWorkoutAndNutritionData(User $user, bool $includeCompletedHistory): void
    {
        $workoutPlan = WorkoutPlan::factory()
            ->for($user)
            ->create([
                'name' => 'Força i recomposició',
                'status' => 'active',
                'starts_on' => now()->startOfWeek(),
                'cycle_length' => 4,
                'mesocycle_number' => 2,
                'phase' => 'build',
                'phase_week' => 2,
            ]);

        $plannedWorkout = WorkoutDay::factory()
            ->for($user)
            ->for($workoutPlan, 'plan')
            ->withBlocks()
            ->create([
                'title' => 'Lower body',
                'focus' => 'lowerBody',
                'scheduled_for' => now()->addDay()->startOfDay(),
                'status' => 'planned',
            ]);

        if ($includeCompletedHistory) {
            $completedWorkout = WorkoutDay::factory()
                ->for($user)
                ->for($workoutPlan, 'plan')
                ->withBlocks()
                ->create([
                    'title' => 'Upper body',
                    'focus' => 'upperBody',
                    'scheduled_for' => now()->subDays(2)->startOfDay(),
                    'started_at' => now()->subDays(2)->setTime(18, 0),
                    'completed_at' => now()->subDays(2)->setTime(19, 5),
                    'status' => 'completed',
                    'did_count_toward_streak' => true,
                    'active_duration_seconds' => 3900,
                    'active_calories_burned' => 420,
                    'total_volume_kg' => 6240,
                ]);

            $completedWorkout->load('exercises');

            $completedWorkout->exercises->each(function ($plannedExercise): void {
                ExerciseResult::factory()
                    ->for($plannedExercise, 'plannedExercise')
                    ->create(['completed_at' => now()->subDays(2)->setTime(19, 0)]);
            });
        }

        NutritionPlan::factory()
            ->for($user)
            ->complete()
            ->create(['source_workout_plan_id' => $workoutPlan->id]);

        $nutritionLogMedia = NutritionLogMedia::factory()->create();

        NutritionLogEntry::factory()
            ->count(6)
            ->sequence(fn (Sequence $sequence) => [
                'logged_at' => now()->subDays($sequence->index)->setTime(12, 30),
                'is_favorite' => $sequence->index === 0,
                'nutrition_log_media_id' => $sequence->index === 0 ? $nutritionLogMedia->id : null,
            ])
            ->for($user)
            ->withItems()
            ->create();
    }
}
