<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_app_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_health_kit_enabled')->default(false);
            $table->boolean('is_watch_auto_tracking_enabled')->default(false);
            $table->boolean('is_screen_on_during_workout_enabled')->default(false);
            $table->boolean('is_notifications_enabled')->default(false);
            $table->boolean('is_full_focus_mode_enabled')->default(false);
            $table->json('full_focus_selection')->nullable();
            $table->boolean('has_seen_notification_request')->default(false);
            $table->boolean('has_seen_body_progress_tutorial')->default(false);
            $table->boolean('has_rated_app')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('training_preferences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('goal')->default('generalFitness');
            $table->string('lifestyle')->default('sedentary');
            $table->string('gym_type')->default('smallGym');
            $table->string('general_training_level')->default('beginner');
            $table->string('training_split_preference')->default('automatic');
            $table->unsignedSmallInteger('preferred_workout_duration_minutes')->default(50);
            $table->text('workout_planner_notes')->nullable();
            $table->json('workout_days');
            $table->json('custom_workout_focuses')->nullable();
            $table->json('movement_restrictions')->nullable();
            $table->string('body_composition_phase')->nullable();
            $table->boolean('skips_warmups')->default(false);
            $table->boolean('skips_cooldowns')->default(false);
            $table->boolean('skips_rest_periods')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('training_locations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('location_details')->nullable();
            $table->string('display_address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('equipment');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'sort_order']);
        });

        Schema::create('body_metric_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->decimal('value', 10, 3);
            $table->timestamp('recorded_at');
            $table->string('source')->default('manual');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'recorded_at']);
        });

        Schema::create('body_progress_photo_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('profile_photo_path')->nullable();
            $table->string('front_photo_path')->nullable();
            $table->string('torso_photo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('nutrition_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('dietary_style')->default('standard');
            $table->unsignedTinyInteger('meals_per_day_preference')->default(3);
            $table->unsignedTinyInteger('preferred_eating_window_start_hour')->nullable();
            $table->unsignedTinyInteger('preferred_eating_window_end_hour')->nullable();
            $table->json('excluded_foods')->nullable();
            $table->json('allergies_and_intolerances')->nullable();
            $table->string('cooking_effort')->default('medium');
            $table->string('budget_sensitivity')->default('medium');
            $table->json('preferred_protein_sources')->nullable();
            $table->json('dislikes')->nullable();
            $table->boolean('wants_meal_suggestions')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('exercise_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('exercise');
            $table->string('level')->default('beginner');
            $table->decimal('working_weight', 8, 2)->nullable();
            $table->decimal('estimated_one_rep_max', 8, 2)->nullable();
            $table->unsignedSmallInteger('max_reps')->nullable();
            $table->unsignedSmallInteger('preferred_rep_range_min')->nullable();
            $table->unsignedSmallInteger('preferred_rep_range_max')->nullable();
            $table->unsignedSmallInteger('typical_duration_minutes')->nullable();
            $table->decimal('typical_distance_km', 8, 3)->nullable();
            $table->unsignedInteger('typical_pace_seconds_per_km')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'exercise']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercise_profiles');
        Schema::dropIfExists('nutrition_profiles');
        Schema::dropIfExists('body_progress_photo_sessions');
        Schema::dropIfExists('body_metric_entries');
        Schema::dropIfExists('training_locations');
        Schema::dropIfExists('training_preferences');
        Schema::dropIfExists('user_app_settings');
    }
};
