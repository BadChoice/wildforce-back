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
        Schema::create('workout_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('goal')->default('generalFitness');
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedInteger('mesocycle_number')->nullable();
            $table->string('phase')->nullable();
            $table->unsignedSmallInteger('phase_week')->nullable();
            $table->unsignedSmallInteger('cycle_length')->nullable();
            $table->string('body_composition_phase')->nullable();
            $table->timestamp('starts_on')->nullable();
            $table->string('profile_settings_hash')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('workout_days', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('workout_plan_id')->nullable();
            $table->string('title');
            $table->string('focus')->default('fullBody');
            $table->string('status')->default('planned');
            $table->boolean('did_count_toward_streak')->default(false);
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->string('intended_weekday')->nullable();
            $table->string('day_type')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedSmallInteger('estimated_duration_minutes')->nullable();
            $table->string('creation_source')->default('generated');
            $table->unsignedInteger('active_duration_seconds')->nullable();
            $table->decimal('active_calories_burned', 9, 2)->nullable();
            $table->decimal('average_heart_rate', 6, 2)->nullable();
            $table->decimal('maximum_heart_rate', 6, 2)->nullable();
            $table->decimal('total_volume_kg', 12, 3)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('workout_plan_id')->references('id')->on('workout_plans')->nullOnDelete();
            $table->index(['user_id', 'scheduled_for']);
            $table->index(['workout_plan_id', 'order_index']);
        });

        Schema::create('workout_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workout_day_id');
            $table->string('type')->default('standard');
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->unsignedSmallInteger('rounds')->default(1);
            $table->unsignedInteger('rest_after_block_seconds')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('workout_day_id')->references('id')->on('workout_days')->cascadeOnDelete();
            $table->index(['workout_day_id', 'order_index']);
        });

        Schema::create('planned_exercises', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workout_day_id');
            $table->uuid('workout_block_id')->nullable();
            $table->string('exercise');
            $table->string('section')->nullable();
            $table->unsignedSmallInteger('order_index')->default(0);
            $table->unsignedSmallInteger('sets')->nullable();
            $table->unsignedSmallInteger('reps_min')->nullable();
            $table->unsignedSmallInteger('reps_max')->nullable();
            $table->json('target_reps')->nullable();
            $table->decimal('target_weight_kg', 8, 2)->nullable();
            $table->json('target_weights_kg')->nullable();
            $table->unsignedSmallInteger('target_duration_minutes')->nullable();
            $table->unsignedInteger('target_duration_seconds')->nullable();
            $table->decimal('target_distance_km', 8, 3)->nullable();
            $table->unsignedInteger('target_pace_seconds_per_km')->nullable();
            $table->unsignedInteger('rest_seconds')->nullable();
            $table->json('set_style_configuration')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('workout_day_id')->references('id')->on('workout_days')->cascadeOnDelete();
            $table->foreign('workout_block_id')->references('id')->on('workout_blocks')->nullOnDelete();
            $table->index(['workout_day_id', 'order_index']);
        });

        Schema::create('exercise_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('planned_exercise_id');
            $table->string('feedback')->default('justRight');
            $table->timestamp('completed_at');
            $table->unsignedSmallInteger('completed_sets')->nullable();
            $table->unsignedSmallInteger('completed_reps')->nullable();
            $table->decimal('completed_weight', 8, 2)->nullable();
            $table->json('per_set_reps')->nullable();
            $table->json('per_set_weights_kg')->nullable();
            $table->unsignedSmallInteger('completed_duration_minutes')->nullable();
            $table->unsignedInteger('completed_duration_seconds')->nullable();
            $table->decimal('completed_distance_km', 8, 3)->nullable();
            $table->unsignedInteger('completed_pace_seconds_per_km')->nullable();
            $table->json('watch_set_summary')->nullable();
            $table->json('watch_rep_summaries')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('planned_exercise_id')->references('id')->on('planned_exercises')->cascadeOnDelete();
            $table->index(['planned_exercise_id', 'completed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercise_results');
        Schema::dropIfExists('planned_exercises');
        Schema::dropIfExists('workout_blocks');
        Schema::dropIfExists('workout_days');
        Schema::dropIfExists('workout_plans');
    }
};
