<?php

use App\Enums\CoachingEnrollmentStatus;
use App\Models\BodyMetricEntry;
use App\Models\CoachingEnrollment;
use App\Models\ExerciseProfile;
use App\Models\NutritionPlan;
use App\Models\NutritionProfile;
use App\Models\TrainingLocation;
use App\Models\TrainingPreference;
use App\Models\User;
use App\Models\UserAppSettings;
use App\Models\WorkoutDay;
use App\Models\WorkoutPlan;
use Illuminate\Support\Str;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('non-administrators are redirected from the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertRedirect(route('workout-plans.index'));
});

test('administrators can visit the dashboard', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('dashboard'))->assertOk();
});

test('dashboard shows each user plan counts', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create([
        'name' => 'Alex Morgan',
        'email' => 'alex@example.com',
    ]);
    $workoutPlan = new WorkoutPlan;
    $workoutPlan->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'name' => 'Strength plan',
    ])->save();

    $customWorkout = new WorkoutDay;
    $customWorkout->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'title' => 'Custom workout',
    ])->save();

    $plannedWorkout = new WorkoutDay;
    $plannedWorkout->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'workout_plan_id' => $workoutPlan->id,
        'title' => 'Planned workout',
    ])->save();

    $nutritionPlan = new NutritionPlan;
    $nutritionPlan->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'starts_on' => now(),
    ])->save();

    $this->actingAs($admin);

    $this->get(route('dashboard'))
        ->assertSee('Users and plans')
        ->assertSeeInOrder([
            'Alex Morgan',
            'alex@example.com',
            '1',
            '1',
            '1',
        ]);
});

test('dashboard displays the selected user training details', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create([
        'name' => 'Alex Morgan',
        'email' => 'alex@example.com',
    ]);

    $trainingPreference = new TrainingPreference;
    $trainingPreference->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'goal' => 'buildStrength',
        'general_training_level' => 'intermediate',
        'workout_days' => ['monday', 'wednesday'],
    ])->save();

    $exerciseProfile = new ExerciseProfile;
    $exerciseProfile->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'exercise' => 'Back squat',
        'working_weight' => 100,
    ])->save();

    $workoutPlan = new WorkoutPlan;
    $workoutPlan->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'name' => 'Strength foundation',
        'status' => 'active',
    ])->save();

    $workoutDay = new WorkoutDay;
    $workoutDay->forceFill([
        'id' => (string) Str::uuid(),
        'user_id' => $user->id,
        'workout_plan_id' => $workoutPlan->id,
        'title' => 'Lower body',
        'focus' => 'lowerBody',
    ])->save();

    $this->actingAs($admin);

    Livewire::test('dashboard.user-plan-list')
        ->call('selectUser', $user->id)
        ->assertSee('Alex Morgan')
        ->assertSee('https://khbsrnhvonggicfmhcpg.supabase.co/storage/v1/object/public/wildfit/avatars/'.$user->id.'.png')
        ->assertSee('Training profile')
        ->assertSee('Build Strength')
        ->assertSee('Strength foundation')
        ->assertSee('Lower body')
        ->assertSee('Progression analysis')
        ->call('openProgressionAnalysis')
        ->assertSet('showProgressionAnalysis', true)
        ->assertSee('Mesocycle timeline')
        ->call('closeProgressionAnalysis')
        ->assertSet('showProgressionAnalysis', false)
        ->call('selectTab', 'exercise-profiles')
        ->assertSee('Exercise profiles')
        ->assertSee('Back squat')
        ->assertSee('100.00 kg');
});

test('dashboard displays subscription information for a selected user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create([
        'name' => 'Demo User',
        'email' => 'demo@example.com',
    ]);

    $this->actingAs($admin);

    Livewire::test('dashboard.user-plan-list')
        ->assertSee('Trial')
        ->assertSee('Active')
        ->call('selectUser', $user->id)
        ->call('selectTab', 'subscription')
        ->assertSee('Subscription details and current access.')
        ->assertSee('Trial')
        ->assertSee('Internal')
        ->assertSee('Access ends');
});

test('dashboard displays app settings for a selected user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    UserAppSettings::factory()->for($user)->create([
        'is_full_focus_mode_enabled' => true,
        'full_focus_selection' => ['workouts', 'nutrition'],
    ]);

    $this->actingAs($admin);

    Livewire::test('dashboard.user-plan-list')
        ->call('selectUser', $user->id)
        ->call('selectTab', 'app-settings')
        ->assertSet('selectedTab', 'app-settings')
        ->assertSee('Full focus mode')
        ->assertSee('Workouts, Nutrition');
});

test('dashboard displays the nutrition profile for a selected user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    NutritionProfile::factory()->for($user)->create([
        'dietary_style' => 'mediterranean',
        'preferred_eating_window_start_hour' => 8,
        'preferred_eating_window_end_hour' => 21,
        'preferred_protein_sources' => ['chicken', 'greekYogurt'],
    ]);

    $this->actingAs($admin);

    Livewire::test('dashboard.user-plan-list')
        ->call('selectUser', $user->id)
        ->call('selectTab', 'nutrition')
        ->assertSee('Nutrition profile')
        ->assertSee('Mediterranean')
        ->assertSee('08:00 – 21:00')
        ->assertSee('Chicken, Greek Yogurt');
});

test('dashboard previews the next plan prompt and response schema for a selected user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    TrainingPreference::factory()->for($user)->create([
        'goal' => 'buildMuscle',
        'workout_days' => ['monday'],
    ]);
    TrainingLocation::factory()->for($user)->create([
        'equipment' => ['bodyweight'],
    ]);
    $this->actingAs($admin);

    Livewire::test('dashboard.user-plan-list')
        ->call('selectUser', $user->id)
        ->assertSee('Next plan prompt')
        ->call('openNextPlanPrompt')
        ->assertSet('planPromptPreview.isOpen', true)
        ->assertSee('Client context')
        ->assertSee('Response schema')
        ->assertSee('workoutDays');
});

test('dashboard displays body metric charts for a selected user', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $bodyMetric = new BodyMetricEntry;
    $bodyMetric->forceFill([
        'user_id' => $user->id,
        'type' => 'bodyFatPercentage',
        'value' => 18.4,
        'recorded_at' => '2026-09-20 10:00:00',
    ])->save();
    $this->actingAs($admin);

    Livewire::test('dashboard.user-plan-list')
        ->call('selectUser', $user->id)
        ->call('selectTab', 'body-metrics')
        ->assertSee('Body Fat Percentage')
        ->assertSee('Latest: 18.400');
});

test('dashboard displays a user coaching relationships', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create(['name' => 'Alex Morgan']);
    $coach = User::factory()->create(['name' => 'Jordan Coach']);
    $client = User::factory()->create(['name' => 'Taylor Client']);

    CoachingEnrollment::create([
        'client_user_id' => $user->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active,
        'starts_at' => '2026-09-01 09:00:00',
    ]);
    CoachingEnrollment::create([
        'client_user_id' => $client->id,
        'coach_user_id' => $user->id,
        'status' => CoachingEnrollmentStatus::Paused,
        'starts_at' => '2026-09-15 09:00:00',
    ]);

    $this->actingAs($admin);

    Livewire::test('dashboard.user-plan-list')
        ->call('selectUser', $user->id)
        ->call('selectTab', 'coaching')
        ->assertSee('Coaches')
        ->assertSee('Jordan Coach')
        ->assertSee('Coaching')
        ->assertSee('Taylor Client')
        ->assertSee('Paused')
        ->assertDontSee('Add coach')
        ->assertSee('Add client');
});

test('dashboard assigns an active user as a coach', function () {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();
    $coach = User::factory()->create(['name' => 'Jordan Coach']);
    User::factory()->withoutSubscription()->create(['name' => 'Inactive User']);

    $this->actingAs($admin);

    Livewire::test('dashboard.user-plan-list')
        ->call('selectUser', $user->id)
        ->call('selectTab', 'coaching')
        ->assertSee('Add coach')
        ->call('openCoachingEnrollmentForm', 'coach')
        ->assertSet('showCoachingEnrollmentForm', true)
        ->assertSee('Jordan Coach')
        ->set('relatedUserId', $coach->id)
        ->call('saveCoachingEnrollment')
        ->assertSet('showCoachingEnrollmentForm', false)
        ->assertSee('Jordan Coach')
        ->assertDontSee('Add coach');

    $this->assertDatabaseHas('coaching_enrollments', [
        'client_user_id' => $user->id,
        'coach_user_id' => $coach->id,
        'status' => CoachingEnrollmentStatus::Active->value,
    ]);
});

test('dashboard manages a coaching relationship status', function () {
    $admin = User::factory()->admin()->create();
    $coach = User::factory()->create();
    $client = User::factory()->create(['name' => 'Taylor Client']);

    $this->actingAs($admin);

    Livewire::test('dashboard.user-plan-list')
        ->call('selectUser', $coach->id)
        ->call('selectTab', 'coaching')
        ->call('openCoachingEnrollmentForm', 'client')
        ->set('relatedUserId', $client->id)
        ->call('saveCoachingEnrollment')
        ->assertSee('Taylor Client');

    $enrollment = CoachingEnrollment::query()->sole();

    Livewire::test('dashboard.user-plan-list')
        ->call('selectUser', $coach->id)
        ->call('pauseCoachingEnrollment', $enrollment->id);

    $this->assertDatabaseHas('coaching_enrollments', [
        'id' => $enrollment->id,
        'status' => CoachingEnrollmentStatus::Paused->value,
    ]);

    Livewire::test('dashboard.user-plan-list')
        ->call('selectUser', $coach->id)
        ->call('endCoachingEnrollment', $enrollment->id);

    $this->assertDatabaseHas('coaching_enrollments', [
        'id' => $enrollment->id,
        'status' => CoachingEnrollmentStatus::Ended->value,
    ]);

    Livewire::test('dashboard.user-plan-list')
        ->call('selectUser', $coach->id)
        ->call('reactivateCoachingEnrollment', $enrollment->id);

    $this->assertDatabaseHas('coaching_enrollments', [
        'id' => $enrollment->id,
        'status' => CoachingEnrollmentStatus::Active->value,
        'ends_at' => null,
    ]);
});
